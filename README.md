# GEANT WebTVBundle (GEANT)
Installation
------------

Steps 1 and 2 requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.


### Step 1: Introduce repository in the root project composer.json

Open a command console, enter your project directory and execute the
following command to add this repo:

```bash
$ composer config repositories.pumukitgeantwebtvbundle vcs http://gitlab.teltek.es/pumukit2/pumukitgeantwebtvbundle.git
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
$ cd /path/to/pumukit2/
$ php app/console pumukit:install:bundle Pumukit/Geant/WebTVBundle/PumukitGeantWebTVBundle
```

### Step 4: Install the Podcast bundle.

For this bundle to work propertly it's necessary to also install the Podcast bundle:
```bash
$ php app/console pumukit:install:bundle Pumukit/PodcastBundle/PumukitPodcastBundle
```


### Step 5: Update assets

```bash
$ cd /path/to/pumukit2/
$ php app/console cache:clear
$ php app/console cache:clear --env=prod
$ php app/console assets:install
```

## Other

### Feed Sync command

The following command is provided to sync the Géant Feed with PuMuKIT 2 database. It can be executed manually or using a cron.
```bash
php app/console --env=prod geant:syncfeed:import
```
