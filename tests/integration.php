<?php

use craft\cache\ElementQueryTagDependency;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use craft\elements\User;
use craft\enums\PropagationMethod;
use craft\events\RegisterElementSourcesEvent;
use craft\fieldlayoutelements\TitleField;
use craft\models\EntryType;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use craft\models\Site;
use craft\services\ElementSources;
use Noo\CraftSidebarRelations\SidebarRelations;
use yii\base\Event;

function expectValue(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

// Never read a consuming application's config or connect to an existing database.
$base = getenv('CRAFT_BASE_PATH');
if (! is_string($base) || ! preg_match('~^/tmp/sidebar-relations\.[a-zA-Z0-9]+/project$~D', $base)
    || getenv('CRAFT_DB_DSN') !== 'pgsql:host='.dirname($base).';dbname=sidebar_test') {
    throw new RuntimeException('Run with composer test.');
}
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/vendor/craftcms/cms/bootstrap/console.php';
$mode = $argv[1];
if ($mode === 'install') {
    exit($app->runAction('install/craft', [
        'username' => 'admin', 'email' => 'admin@example.test', 'password' => bin2hex(random_bytes(20)),
        'siteName' => 'Test', 'siteUrl' => 'https://example.test', 'language' => 'en-US', 'interactive' => false,
    ]));
}

$sites = $app->getSites();
$primary = $sites->getPrimarySite();
$entries = $app->getEntries();
$elements = $app->getElements();
if ($mode === 'seed') {
    $admin = User::find()->admin(true)->one();
    $app->getUser()->setIdentity($admin);
    $french = new Site(['groupId' => $primary->groupId, 'name' => 'French', 'handle' => 'french', 'language' => 'fr', 'hasUrls' => false]);
    expectValue($sites->saveSite($french), 'Cannot create French site: '.json_encode($french->getErrors()));
    $editor = new User(['username' => 'editor', 'email' => 'editor@example.test']);
    expectValue($elements->saveElement($editor), 'Cannot create editor: '.json_encode($editor->getErrors()));
    $type = new EntryType(['name' => 'Page', 'handle' => 'page']);
    $type->getFieldLayout()->setTabs([['name' => 'Content', 'elements' => [new TitleField]]]);
    expectValue($entries->saveEntryType($type), 'Cannot create entry type: '.json_encode($type->getErrors()));
    foreach (['articles', 'categories', 'authors'] as $handle) {
        $section = new Section([
            'name' => ucfirst($handle), 'handle' => $handle,
            'type' => $handle === 'articles' ? Section::TYPE_STRUCTURE : Section::TYPE_CHANNEL,
            'entryTypes' => [$type], 'propagationMethod' => PropagationMethod::None,
            'siteSettings' => array_map(fn ($site) => new Section_SiteSettings(['siteId' => $site->id]), [$primary, $french]),
        ]);
        expectValue($entries->saveSection($section), "Cannot create $handle: ".json_encode($section->getErrors()));
    }
    $categories = $entries->getSectionByHandle('categories');
    $authors = $entries->getSectionByHandle('authors');
    $articles = $entries->getSectionByHandle('articles');
    $app->getUserPermissions()->saveUserPermissions($editor->id, [
        'accessCp', "editSite:$primary->uid", "viewEntries:$categories->uid", "viewEntries:$articles->uid",
    ]);
    foreach ([
        [$categories, $primary, $admin, 'A peer category'],
        [$categories, $primary, $editor, 'B own category'],
        [$categories, $french, $admin, 'Catégorie'],
        [$authors, $french, $admin, 'Auteur'],
        [$authors, $primary, $editor, 'Hidden section'],
    ] as [$section, $site, $author, $title]) {
        $entry = new Entry(['sectionId' => $section->id, 'siteId' => $site->id, 'typeId' => $type->id, 'authorId' => $author->id, 'title' => $title]);
        expectValue($elements->saveElement($entry), "Cannot create $title: ".json_encode($entry->getErrors()));
    }
    exit(0);
}

$identity = match ($mode) {
    'admin', 'invalid' => User::find()->admin(true)->one(),
    'editor' => User::find()->username('editor')->status(null)->one(),
    'guest' => null,
};
$app->getUser()->setIdentity($identity);
if ($mode === 'editor') {
    $categories = $entries->getSectionByHandle('categories');
    expectValue($identity && $identity->can("viewEntries:$categories->uid") && ! $identity->can("viewPeerEntries:$categories->uid"), 'Editor fixture must be allowed to view only their own categories');
}
$key = 'section:'.$entries->getSectionByHandle('articles')->uid;
Event::on(Entry::class, Entry::EVENT_REGISTER_SOURCES, function (RegisterElementSourcesEvent $event) use ($key) {
    foreach ($event->sources as &$source) {
        if (($source['key'] ?? null) === $key) {
            $source['nested'][] = ['key' => 'extension', 'label' => 'Existing child'];
            $source['criteria']['archived'] = false;
        }
    }
});
new SidebarRelations('sidebar-relations', $app);

// Inspect the actual element query, as well as the rows it returns from Postgres.
$limits = [];
Event::on(EntryQuery::class, EntryQuery::EVENT_BEFORE_PREPARE, function (Event $event) use (&$limits) {
    $query = $event->sender;
    if (array_values((array) $query->select) === ['elements.id', 'elements_sites.title']) {
        expectValue($query->editable === true && $query->queryCacheDependency instanceof ElementQueryTagDependency, 'Title query missing visibility or invalidation');
        $limits[] = $query->limit;
    }
});
$sourceFor = function (string $context) use ($key): array {
    Entry::forgetSources();

    return array_column(Entry::sources($context), null, 'key')[$key] ?? [];
};
$modal = $sourceFor(ElementSources::CONTEXT_MODAL);
expectValue(count($modal['nested']) === 1 && $limits === [], 'Picker should not build nested relation sources');
$parent = $sourceFor(ElementSources::CONTEXT_INDEX);
if ($mode === 'guest') {
    expectValue(array_column($parent['nested'] ?? [], 'key') === ['extension'] && $limits === [], 'Unauthenticated request queried titles');
} else {
    $children = $parent['nested'];
    expectValue(array_shift($children)['key'] === 'extension', 'Existing child was overwritten');
    $expected = match ($mode) {
        'admin' => ['A peer category', 'Catégorie', 'Hidden section', 'Auteur'],
        'editor' => ['B own category'],
        'invalid' => [],
    };
    $labels = array_column($children, 'label');
    expectValue($expected === $labels, 'Unexpected nested labels for '.$mode.': '.json_encode($labels));
    foreach ($children as $child) {
        foreach (['data', 'defaultSort', 'structureId', 'structureEditable'] as $attribute) {
            expectValue($parent[$attribute] === $child[$attribute], "Lost parent $attribute");
        }
        $id = $child['criteria']['relatedTo'];
        expectValue($child['criteria'] === $parent['criteria'] + ['relatedTo' => $id], 'Lost parent permission criteria');
        $site = in_array($child['label'], ['Catégorie', 'Auteur'], true) ? $sites->getSiteByHandle('french') : $primary;
        expectValue($child['sites'] === [$site->id] && $child['key'] === "relatedTo:$id:$site->id", 'Incorrect localized source key or site');
    }
    if ($mode !== 'invalid') {
        expectValue(in_array(1, $limits, true), 'Configured limit missing');
    }
    if ($mode === 'admin') {
        expectValue(in_array(100, $limits, true), 'Default query limit missing');
        $entry = Entry::find()->id($children[0]['criteria']['relatedTo'])->one();
        $entry->title = 'A changed category';
        expectValue($elements->saveElement($entry), 'Cannot update cached relation');
        expectValue($sourceFor(ElementSources::CONTEXT_INDEX)['nested'][1]['label'] === $entry->title, 'Title cache survived an entry update');
    }
}
echo "PASS $mode: Craft entry sources\n";
