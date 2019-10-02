# Blurp!

This plugin is a fancy image lazy loader. It scans all the content filtered 
by `the_content`, and replaces the images with an embedded tiny thumbnail
(40px by proportional height). This is injected inline into the page and 
a blur filter is applied to make it look nicer. As the user scrolls through
the page, once the blurry image comes into view, it is replaced by the
original image.

## Notes

This plugin does quite a lot of file reading and processing, which can
increase the TTFB. It is highly recommend that if this plugin is utilized,
you use a WordPress cache plugin like Cache Enabler, so that the images
are not being reprocessed every time a page is loaded.

## Features

### WebP

This plugin is WebP aware! The server-side script checks to see if a .webp
file exists for the original image, and if the browser is compatible,
the front-end script will upgrade the original image to the webp version.
This methodology is cache and CDN friendly!
