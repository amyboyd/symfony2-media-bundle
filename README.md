## Sample Use ##

See `Entity/SampleImage.php.sample`

## Install ##

* If you use Git, run: `git submodule add https://github.com/avalanche123/Imagine.git path/to/bundles/MT/Bundle/MediaBundle/lib/imagine`

* If you don't use Git, download the Imagine library and put it in your bundles directory.

* Enable MTMediaBundle bundle in AppKernel.php

* Review `app/console doctrine:schema:update --dump-sql`

* Run `app/console doctrine:schema:update --force` if the above was OK.
