Usage
----------

Extend the Image/File classes. See `Entity/SampleImage.php.sample` for a sample.

If you add more image thumbnail sizes to `getAllSizes()`, or you change the `postResizeHook()`, you can re-generate all thumbnails with the command `mt-media:recreate-thumbnails-command`.

Install
-------

* `git checkout git@github.com:amyboyd/symfony2-media-bundle.git vendor/bundles/MT/Bundle/MediaBundle`

* `git checkout git@github.com:avalanche123/Imagine.git vendor/bundles/MT/Bundle/MediaBundle/lib/imagine`

* Enable the MTMediaBundle bundle in `AppKernel.php`

* Review `app/console doctrine:schema:update --dump-sql`

* Run `app/console doctrine:schema:update --force` if the above was OK.
