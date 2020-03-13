# Syncing data from an API into Drupal 8 Entities

**DrupalCamp London 2020 Training - Detailed instructions**

## Table of contents

1. Setting up a Drupal 8 site
1. Planning & mapping
1. Create the entity to store the data in
1. Create our skeleton module
1. Working with the external API
1. Creating the entity programmatically
1. Working with the Batch API

Bonus:

* BONUS 1: Use the Migrate API instead of (or as well as) the Batch API

Other things you could do with this:

* BONUS 2: Refactor BatchProcessor
* BONUS 3: Create a Views Query Plugin
* BONUS 4: Create a batch hook_update_N

## 1 Setting up a Drupal 8 site

Make sure you have a Drupal 8 site up and running. This should ideally be installed via composer and ideally you should have access to Drupal Console but neither required (if you are using DDEV, Drupal Console is available).

### 1.1 Installing via composer

[View the Drupal 8 docs here](https://www.drupal.org/docs/develop/using-composer/using-composer-to-install-drupal-and-manage-dependencies#s-using-drupalrecommended-project)

#### 1.1.1 Detailed steps to install using DDEV

1. Create a project like `composer create-project drupal/recommended-project drupalcamp-london-2020-training`
1. Enter that directory, so `cd drupalcamp-london-2020-training`
1. Run `ddev config` in that directory: set the document root to `web` and set the project type to `drupal8`
1. Start DDEV and SSH into the container `ddev start` then `ddev ssh` - this is so we run composer within the container so we ensure it installs dependencies that match the php and mysql versions of the DDEV container.
1. Find the URL of the site, so `ddev describe`
1. Install Drupal using the standard profile (the DDEV database configuration is username `db`, password `db`, database name `db`, and advanced > host `db`)
1. Login to the Drupal site (you can also do this with `drush` using `drush uli 1` to login as user 1)

### 1.2 Turn off caching and turn on error handling.

1. In your web/sites/default/settings.php uncomment these lines so your site includes a settings.local.php (for a real project, you then put settings.local.php into .gitignore so it applies only to your local environment. (If you ran `ddev config` to get your site, this probably got removed, you can add the below after the settings.ddev.php).
```
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
```
2. Copy the web/sites/example.settings.local.php into web/sites/*default*/settings.local.php

You can test this works by adding and you should see multiple lines of errors which tells us which functions were called, which files were loaded, and where the error occurred:
```
trigger_error('hello');
die();
```
Now delete that.

### 1.3 Install devel and kint

1. Install the devel module from the project root (this is one level up from the web folder, get there with `cd ..` if you are in the web folder), run `composer require drupal/devel` and enable devel and kint from the web root `drush pm-enable devel, kint -y`
1. Add the [kint max levels config](https://gist.github.com/JPustkuchen/a5f1eaeb7058856b7ef087b028ffdfeb) to your project to prevent memory errors if dumping large objects
1. Checking if Drupal Console is installed
1. Navigate to `web` dir within container.
1. Run `drupal` - if you see a list of commands, its installed!
   1. If not go to the project root (ie, one level up from web) and run `composer require drupal/console:~1.0 --prefer-dist --optimize-autoloader`
   1. If you have permissions issues make sure composer can write to web/sites/default/ , so `sudo chmod u+w web/sites/default/`

## 2 Planning & mapping

* The sample API can be found here: https://reqres.in/- we will start by looking at the GET posts endpoint (https://reqres.in/api/posts and https://reqres.in/api/posts?page=2 etc).
* We can decide what Entity we want this to eventually go to in Drupal 8.
* We can create a spreadsheet to help decide what to map where. This would be something you could typically discuss with your client. Below is an example as a starting point.

![Mapping table example](assets/images/mapping-table.png "Logo Title Text 1")

* Note that we should store the API id in the Drupal 8 site so we eventually know what to update, so we can create a field like ‘External ID’ on the Entity (when using Migrate API, Migrate does the storage of the API id for you, but it is needed for the Batch API).

## 3 Create the entity to store the data in

1. Create a node type, eg `Paint Can` (machine name `paint_can`)
1. Create fields matching the data (note that this is not required, later we will see how we can transform the data on it's way into Drupal):
   1. Year
   1. Colour
   1. Pantone Value

## 4 Create our skeleton module

### 4.1 Add the required MODULE_NAME.info.yml file

[See the documentation here](https://www.drupal.org/docs/8/creating-custom-modules/let-drupal-8-know-about-your-module-with-an-infoyml-file). Eg, at `sync_external_posts.info.yml` add your info yml contents like

```
name: 'Sync External Posts'
type: module
description: 'A module to sync external posts into the site.'
core: 8.x
package: 'Custom'
```

### 4.2 Create a MODULE_NAME.permissions.yml file

[See an example here](https://api.drupal.org/api/drupal/core!modules!node!node.permissions.yml/8.2.x)

It can be as simple as this:
```
use api batch form:
  title: 'Use the API Batch Form'
```

### 4.3 Create a MODULE_NAME.routing.yml file

The key should be MODULE_NAME.SOME_ID. We will set an admin path and specify a form class `ApiBatchForm` within our namespace.
```
sync_external_posts.api_batch_form:
  path: '/admin/api-batch-form'
  defaults:
    _form: '\Drupal\sync_external_posts\Form\ApiBatchForm'
    _title: 'ApiBatchForm'
  requirements:
    _permission: 'use api batch form'
```
Notice here, we also used our permission we created above.

### 4.4 Create our folder structure within our module

- src
  - Form
    - ... files will go here
  - Api
    - ... files will go here
  - Batch
    - ... files will go here
  - Node
    - ... files will go here

## 5 Working with the external API

### 5.1 Start by creating a form

We need to use our class name we specified in the routing.yml,
so create the file in the Form folder `ApiBatchForm.php`. Here is a skeleton:

```
<?php

namespace Drupal\sync_external_posts\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class ApiBatchForm extends FormBase {

  public function getFormId() {
    return 'api_batch_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    // Let's put a submission button here.
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sync posts'),
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // We will eventually start our batch here.
  }

}
```
You can see the [full documentation for the Form class here](https://www.drupal.org/docs/8/api/form-api/introduction-to-form-api).

### 5.2 Enable the module

```
drush pm-enable sync_external_posts
```

### 5.3 Check that we can output debugs there

1. Visit the URL we set up in the routing.yml and check that the form loads.
1. Add `dvm('hello');` and `ksm('hello');` inside our `buildForm` method of the form class.
1. Check that the output appeared.

### 5.4 Create the API connection service

1. Create a `sync_external_posts.services.yml` and add the service:

```
services:
  sync_external_posts.api_connection:
    class: Drupal\sync_external_posts\Api\ApiConnectionService
    arguments: ['@http_client']
```
We key the service by MODULE_NAME.SOME_ID and set the class to be within the Api folder
to help keep our code maintainable. The arguments tells dependency injection to
pass the `http_client` Drupal Core Service to our service (it's a wrapper for [Guzzle](http://docs.guzzlephp.org/en/stable/)
which is a great tool for working with APIs).

1. Create a skeleton Service class ready to accept our injected `http_client`

```
<?php

namespace Drupal\sync_external_posts\Api;

use GuzzleHttp\ClientInterface;

class ApiConnectionService {

  protected $httpClient;
  protected $apiBaseUrl;

  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
    $this->apiBaseUrl = 'https://reqres.in/api';
  }

}
```

1. Check that we can load our service in our form.

1. We are going to need some additional tools from Drupal. `Json` is a wrapper
for php's `json_decode` that helps handle some security issues. `UrlHelper` helps
us build a query string safely and properly encoded.

```
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;
```

1. Now let's create a method to make the API call, adding this into our class.

```
  public function getRequest($endpoint, array $args = []) {
    $url = $this->apiBaseUrl . $endpoint;
    if ($query = UrlHelper::buildQuery($args)) {
      $url .= '?' . $query;
    }
    $response = $this->httpClient->request('GET', $url);
    if ($response->getStatusCode() === 200) {
      $json_body = $response->getBody();
      return Json::decode($json_body);
    }
    return FALSE;
  }
```

#### 5.4.1 Test connecting to the API

We can put some temporary code into our `buildForm` method of our form again:

```
/** @var \Drupal\sync_external_posts\Api\ApiConnectionService $api_connection */
$api_connection = \Drupal::service('sync_external_posts.api_connection');
$results = $api_connection->getRequest('/posts', [
  'page' => 1,
]);
dvm($results);
```

This should give us a list of results from page one. We can change the page number to `2` to
see the second page of results.

### 5.5 Create an API Paint Can Getting Service

We have now connected to the API; now let's implement one of the APIs endpoints
in new service:

```
<?php

namespace Drupal\sync_external_posts\Api;

class ApiGetPaintCansService {

  protected $apiConnection;
  protected $retrievedPosts = [];

  public function __construct(ApiConnectionService $api_connection) {
    $this->apiConnection = $api_connection;
  }

}
```
We have injected our ApiConnectionService into our class and provided
a place to store retrieved pages to avoid retrieving them twice in a
single request.

We also need to add our new service to the services.yml file:
```
  sync_external_posts.api_get_paint_cans:
    class: Drupal\sync_external_posts\Api\ApiGetPaintCansService
    arguments: ['@sync_external_posts.api_connection']
```

#### 5.5.1 Move our test retrieval of posts into a page

We add a method here to retrieve a page of results, defaulting to page 1.

```
  protected function getPosts($page_number = 1) {
    if (isset($this->retrievedPosts[$page_number])) {
      return $this->retrievedPosts[$page_number];
    }
    $this->retrievedPosts[$page_number] = $this->apiConnection->getRequest('/posts', [
      'page' => $page_number,
    ]);
    return $this->retrievedPosts[$page_number];
  }
```

We can now test that that works:

```
/** @var \Drupal\sync_external_posts\Api\ApiGetPaintCansService $api_get_paint_cans */
$api_get_paint_cans = \Drupal::service('sync_external_posts.api_get_paint_cans');
$results = $api_get_paint_cans->getPosts(1);
dvm($results);
```

#### 5.5.2 Create a method to retrieve just the data

We can see from the API results that the individual items are
stored within the array key 'data' and the rest is meta data.
We can use that meta data later so we will leave our original method.

```
  public function getPostsData($page_number = 1) {
    $results = $this->getPosts($page_number);
    if (isset($results['data'])) {
      return $results['data'];
    }
    return FALSE;
  }
```

#### 5.5.3 Create a method to get a single result

While we can see the API listing returns the same data as the
individual result, this is not normal. Normally if you would retrieve
a list of items, you may just get title, summary, date while retrieving
the individual data will likely give you far more information.

```
  public function getPostData($id) {
    $results = $this->apiConnection->getRequest('/posts/' . $id);
    if (isset($results['data'])) {
      return $results['data'];
    }
    return FALSE;
  }
```

We can test this is working as well:
```
dvm($api_get_paint_cans->getPostData(3));
```

#### 5.5.4 Create a couple methods to retrieve the meta data

If we think of the batch process as a progress bar, we can only show
accurate progress if we know how many items there are in total and
compare that with how many items we have synced thus far.

```
  public function getPostsPerPage() {
    $results = $this->getPosts();
    if (isset($results['per_page'])) {
      return (int) $results['per_page'];
    }
    return 0;
  }

  public function getTotalPosts() {
    $results = $this->getPosts();
    if (isset($results['total'])) {
      return (int) $results['total'];
    }
    return 0;
  }
```

We can test our methods as well:

```
dvm($api_get_paint_cans->getPostsPerPage());
dvm($api_get_paint_cans->getTotalPosts());
```

## 6 Creating the entity programmatically

Add a service to insert or update a node to our services.yml (upsert).
We will add the entity_type.manager Drupal Core service to help us find
and load existing nodes. We alternative also use other tools like EntityQuery.

```
  sync_external_posts.node_paint_can_update:
    class: Drupal\sync_external_posts\Node\NodePaintCanUpdateService
    arguments: ['@entity_type.manager']
```

### 6.1 Create the class

```
<?php

namespace Drupal\sync_external_posts\Node;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;

class NodePaintCanUpdateService {

  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

}
````

### 6.2 Add a method to create the Node

We are now going to need a place to store the external ID on the Node
so we can decide whether to create a new node or update the existing node.

```
  public function upsertNodePaintCan($data) {
    $node = $this->createNewNode($data['id'], $data['name']);
  }

  protected function createNewNode($id, $name) {
    return $this->entityTypeManager->getStorage('node')->create([
      'field_external_id' => $id,
      'title' => $name,
      'type' => 'paint_can',
    ]);
  }
```

### 6.3 Check whether a node already exists

1. Update the upsert method:
```
  public function upsertNodePaintCan($data) {
    $node = $this->getExistingNodeById($data['id']);
    if (!$node) {
      $node = $this->createNewNode($data['id'], $data['name']);
    }
  }
```

1. Add the existing node check query.
```
  protected function getExistingNodeById($id) {
    $node_storage = $this->entityTypeManager->getStorage('node');
    $query = $node_storage->getQuery();
    $query->condition('type', 'paint_can');
    $query->condition('field_external_id', $id);
    $node_ids = $query->execute();
    if ($node_ids) {
      $node_id = reset($node_ids);
      return $node_storage->load($node_id);
    }
    return FALSE;
  }
```

1. Update the details now that we have the node; we can add this to the
upsert method. This is our opportunity to make any modifications.

```
    if ($node) {
      /** @var $node \Drupal\node\Entity\Node */
      $hex_colour = str_replace('#', '', $data['color']);
      $node->setTitle(ucwords($data['name']));
      $node->set('field_colour', $hex_colour);
      $node->set('field_year', $data['year']);
      $node->set('field_pantone_value', $data['pantone_value']);
      $node->setPublished(TRUE);
      $node->save();
    }
```

### 6.4 Test upserting the node from the API data.

## 7 Working with the Batch API


### 7.1 Create a skeleton class for processing our batch

This can go into our Batch folder to help keep our code organised.
```
<?php

namespace Drupal\sync_external_posts\Batch;

use Drupal\sync_external_posts\Api\ApiGetPaintCansService;

class BatchProcessor {

}
```
### 7.2 Creating the batch builder.

Add the batch builder to our submit handler. Let's talk through each bit.
Note that the BatchProcessor methods will be called statically.
```
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch_builder = (new BatchBuilder())
      ->setTitle(t('Syncing Paint Cans'))
      ->setFinishCallback([BatchProcessor::class, 'finishedCallback'])
      ->setInitMessage(t('Batch is starting'))
      ->setProgressMessage(t('Currently syncing paint cans.'))
      ->setErrorMessage(t('Batch has encountered an error'));

    // We can pass additional arguments if we want, such as settings from the
    // form. These would get passed as additional variables to the operation
    // callback method.
    $args = [];
    $batch_builder->addOperation([BatchProcessor::class, 'operationCallback'], $args);
    batch_set($batch_builder->toArray());
  }
```

### 7.3 Creating the batch operation and finished callbacks.

These must be static methods.

```
  public static function operationCallback(&$context) {
    $context['finished'] = 1;
  }

  public static function finishedCallback($success, $results, $operations) {
    if ($success) {

      // The 'success' parameter means no fatal PHP errors were detected.
      $message = t('@count paint cans were synced successfully.', [
        '@count' => count($results),
      ]);
      \Drupal::messenger()->addStatus($message);
    }
    else {

      // A fatal error occurred.
      $message = t('Finished with an error.');
      \Drupal::messenger()->addWarning($message);
    }
  }
```

Let's hit the submit button on our form to see our batch running. We should
see it has process 0 paint cans.

### 7.4 Initialise the sandbox

The &$context standard variables to use in the operation callback are the following two special keys:
- `sandbox` - Every time the operation runs, this variable is passed and contains the sandbox values of previous runs.
- `results` - This allows us to store an array of result IDs which we can use in our finish callback.
- `message` - This get's show above our progress bar as it progresses so the user can know what is currently happening.

Let's see how to use this sandbox variable to handle the batching. We will talk this through:

```
  public static function operationCallback(&$context) {
    /** @var \Drupal\sync_external_posts\Api\ApiGetPaintCansService $api_get_paint_cans */
    $api_get_paint_cans = \Drupal::service('sync_external_posts.api_get_paint_cans');
    if (empty($context['sandbox'])) {
      $context = self::initialiseSandbox($context, $api_get_paint_cans);
    }

    // Nothing to process.
    if (!$context['sandbox']['max']) {
      $context['finished'] = 1;
    }

    // If we haven't yet processed all.
    if ($context['sandbox']['progress'] < $context['sandbox']['max']) {

      // TODO - run through all the items here.
    }

    // When progress equals max, finished is '1' which means completed. Any
    // decimal between '0' and '1' is used to determine the percentage of
    // the progress bar.
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }

  protected static function initialiseSandbox($context, ApiGetPaintCansService $api_get_paint_cans) {
    $context['sandbox'] = [];
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['max'] = $api_get_paint_cans->getTotalPosts();
    $context['sandbox']['per_page'] = $api_get_paint_cans->getPostsPerPage();
    return $context;
  }
```

### 7.5 Batch progress

Let's now replace the `TODO` with the following:
```
      $limit_per_batch = 2;
      $count_this_batch = 0;
      while ($count_this_batch < $limit_per_batch && $context['sandbox']['progress'] < $context['sandbox']['max']) {

        // TODO - Get the individual API result and create the node here.

        // Always increase the progress even if there is an error or we will
        // get stuck in an endless loop. Instead, set error messages, and if
        // you want to stop progressing on an error, set 'finished' to '1'.
        $context['sandbox']['progress']++;

        // Optional message displayed under the progressbar.
        $context['message'] = t('Processing item number "@progress".', [
          '@progress' => $context['sandbox']['progress'],
        ]);

        // Increase the number processed this particular batch.
        $count_this_batch++;
      }
```

### 7.6 Get the API result and create the node

Let's now replace the above `TODO` with the following:

```
        // Determine the current page by seeing the current item number and
        // comparing with the number of results per page.
        $current_page = (int) ceil(($context['sandbox']['progress'] + 1) / $context['sandbox']['per_page']);

        // Get the page of results.
        $api_results_page = $api_get_paint_cans->getPostsData($current_page);

        // If we are at for instance progress '7' when pages are '6' long, on
        // page 2, the index we want is '1'. Getting the remainder of the
        // progress divided by the number per page gives us this.
        $current_index = $context['sandbox']['progress'] % $context['sandbox']['per_page'];
        if (isset($api_results_page[$current_index])) {

          // Let's pretend the individual API call gives us more data than the listing.
          $data = $api_get_paint_cans->getPostData($api_results_page[$current_index]['id']);

          if ($data) {

            // With our data, upsert the paint can.
            /** @var \Drupal\sync_external_posts\Node\NodePaintCanUpdateService $node_paint_can_update */
            $node_paint_can_update = \Drupal::service('sync_external_posts.node_paint_can_update');
            $node_paint_can_update->upsertNodePaintCan($data);

            // Store the ID for the finished callback.
            $context['results'][] = $data['id'];
          }
        }
```

## BONUS 1: Use the Migrate API instead of (or as well as) the Batch API

### Module setup

Install migrate_tools `composer require drupal/migrate_tools` and enable it `drush pm-enable migrate_tools`.

### Create a migrate source plugin

Create this at `/src/Plugin/migrate/source/ApiMigrateSource.php`.
Will talk this through if we hvae time. The comment in the class
name is important: this allows the Plugin API to find the class and
this is what we will reference in our migration.yml file.

We can extend the Core migration SourcePluginBase file, but we
are required to extend its abstract methods.

```
<?php

namespace Drupal\sync_external_posts\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\sync_external_posts\Iterator\ApiPaintCanIterator;

/**
 * Source plugin for API paint cans.
 *
 * @MigrateSource(
 *   id = "api_migrate_source_paint_cans"
 * )
 */
class ApiMigrateSource extends SourcePluginBase {

  public function initializeIterator() {
    return new ApiPaintCanIterator();
  }

  public function __toString() {
    $fields = $this->fields();
    return implode(', ', array_keys($fields));
  }

  public function fields() {
    $fields = [
      'id' => $this->t('Paint Can ID'),
      'name' => $this->t('Name of paint'),
      'year' => $this->t('The year'),
      'color' => $this->t('The colour'),
      'pantone_value' => $this->t('Pantone value'),
    ];

    return $fields;
  }

  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
      ],
    ];
  }

}
```

### Create the Iterator

This is roughly equivalent to our BatchProcessor class. We can create it at
`src/Iterator/ApiPaintCanIterator.php`

We implement Countable because we know the total, but this is not required;
Iteratable is the only thing we are required to implement:

```
<?php

namespace Drupal\sync_external_posts\Iterator;

class ApiPaintCanIterator implements \Iterator, \Countable {

  protected $apiGetPaintCans;
  protected $currentPosition;
  protected $count;
  protected $perPage;
  protected $currentPage;
  protected $currentPageResults;

  public function __construct() {
    $this->apiGetPaintCans = \Drupal::service('sync_external_posts.api_get_paint_cans');
    $this->currentPosition = 0;
    $this->currentPage = 0;
    $this->count = $this->apiGetPaintCans->getTotalPosts();
    $this->perPage = $this->apiGetPaintCans->getPostsPerPage();
  }

  public function count() {
    return $this->count;
  }

  public function current() {
    if (!$this->currentPage) {
      $this->updateIterator();
    }

    // If we are at for instance progress '7' when pages are '6' long, on
    // page 2, the index we want is '1'. Getting the remainder of the
    // progress divided by the number per page gives us this.
    $current_index = $this->key() % $this->perPage;
    if (isset($this->currentPageResults[$current_index])) {
      return $this->currentPageResults[$current_index];
    }
    return FALSE;
  }

  public function key() {
    return $this->currentPosition;
  }

  public function next() {
    $this->currentPosition += 1;
    $this->updateIterator();
  }

  public function rewind() {
    $this->currentPosition = 0;
    $this->updateIterator();
  }

  public function valid() {
    if ($this->currentPosition >= 0 && $this->currentPosition < $this->count()) {
      return TRUE;
    }
    return FALSE;
  }

  protected function updateIterator() {
    // Determine the current page by seeing the current item number and
    // comparing with the number of results per page.
    $current_page = (int) ceil(($this->currentPosition + 1) / $this->perPage);

    // Fetch the page from the API if we don't have it yet.
    if ($this->currentPage != $current_page) {

      // Get the page of results.
      $this->currentPageResults = $this->apiGetPaintCans->getPostsData($current_page);
      $this->currentPage = $current_page;
    }
  }

}
```
The `current` method is the one we send back the data for the
individual item. The `updateIterator` method is our own to
potentially make an API call if we need to. The rest of the methods
are to help whatever script is using the iterator navigate through
the results - all those other methods (+ current) are required
because of the classes we are implementing.

A fully commented version can be found in the repo.

### Create the migration

In `config/install/migrate.migration.paint_cans.yml` add the migration:
```
id: paint_cans
label: 'Migration of Paint Cans from the API'
source:
  plugin: api_migrate_source_paint_cans
process:
  title: name
destination:
  plugin: entity:node
  default_bundle: paint_can
```

### Run the migration

1. Re-import your config (`drush config-import --partial --source=modules/custom/sync_external_posts/config/install/`). You should run this every time you make a change to your migration yml.
1. Check `drush migrate-status` to see your migration.
1. Run the migration, eg `drush migrate-import paint_cans`

To get rid of what you migrated:

1. Rollback the migration, eg `drush migrate-rollback paint_cans`

Tip: Check out [Migrate Scheduler](https://www.drupal.org/project/migrate_scheduler) for
running this at set intervals. Or depending on your host, add a cron job to run the `drush`
commands at your desired intervals.

## BONUS 2: Refactor BatchProcessor

Refactor the BatchProcessor to use the Iterator instead.
We definitely won't get to this in the training.

## BONUS 3: Create a Views Query Plugin

[Lullabot has a great 3 part article tutorial here](https://www.lullabot.com/articles/building-views-query-plugins-dupal-8-part-1)
Tip: Use the Iterator
We definitely won't get to this in the training.

## BONUS 4: Create a batch hook_update_N

[Third and Grove have a good article here](https://www.thirdandgrove.com/insights/using-batch-api-and-hookupdaten-drupal-8/)
Tip: Use the Iterator
