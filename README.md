# GEANT-OER Project: GeantWebTVBundle

Bundle based on [Symfony](http://symfony.com/) to work with the [PuMuKIT2 Video Platform](https://github.com/campusdomar/PuMuKIT2/blob/2.1.x/README.md).

This bundle overrides the [PuMuKIT-2 WebTV Bundle](https://github.com/campusdomar/PuMuKIT2/tree/master/src/Pumukit/WebTVBundle). It has been developed as the Web portal for the Geant-OER project, whose goal is the creation of an European repository of educational multimedia resources for learning.

Installation
------------

Steps 1 and 2 requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.


### Step 1: Introduce repository in the root project composer.json

Open a command console, enter your project directory and execute the
following command to add this repo:

```bash
$ composer config repositories.pumukitgeantwebtvbundle vcs https://github.com/teltek/PuMuKIT2-geant-bundle.git
```


### Step 2: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```bash
$ composer require teltek/pmk2-geant-webtv-bundle dev-master
```


### Step 3: Install the Bundle

Install the bundle by executing the following line command. This command updates the Kernel to enable the bundle (app/AppKernel.php) and loads the routing (app/config/routing.yml) to add the bundle routes\
.

```bash
$ php app/console pumukit:install:bundle Pumukit/Geant/WebTVBundle/PumukitGeantWebTVBundle
```

### Step 4: Install the Podcast bundle and initialize iTunesU tags.

For this bundle to work propertly it's necessary to also install the Podcast bundle:
```bash
$ php app/console pumukit:install:bundle Pumukit/PodcastBundle/PumukitPodcastBundle
$ php app/console podcast:init:tags --force
```


### Step 5: Update assets

```bash
$ php app/console cache:clear
$ php app/console cache:clear --env=prod
$ php app/console assets:install
```

## Other

### Feed Sync script

Execute the following script from the root folder of your PuMuKIT2 proyect (usually, /var/www/pumukit2) specifying the environment's URL to sync the Géant Feed with PuMuKIT 2 database. It can be executed manually or using a cron task.
```bash
$ ./bin/geant_syncfeed_import https://example.com
```
