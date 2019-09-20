
var blurpImages = null;

function blurpImgLoaded ( e ) {
	var self = this;
	self.parentNode.classList.add( 'loaded' );
}

function blurpSwapImages( preloaded ) {

	var box;
	var windowHeight = window.innerHeight|| document.documentElement.clientHeight || document.body.clientHeight

	for ( var i = 0; i < preloaded.length; i += 1 ) {

		if ( preloaded[i].classList.contains( 'seen' ) ) {
			continue;
		}

		box = preloaded[i].getBoundingClientRect();

		if ( box.bottom > 0 && box.top < windowHeight ) {
			preloaded[i].classList.add( 'seen' );
			blurpSwapImage( preloaded[i] );
		}

	}

}

function blurpSwapImage( element ) {

	var img    = document.createElement( 'img' ),
		src    = (element.dataset.src || '') + '',
		srcset = (element.dataset.srcset || '') + '',
		sizes  = (element.dataset.sizes || '') + '';

	var parent = element.parentNode;
	if ( parent.tagName === 'FIGURE' ) {
		parent.classList.add( 'uses-blurp' );
	}

	if ( element.classList.contains( 'webp-ok' ) && window.webpOk ) {
		src += '.webp';
		srcset = srcset.replace(/\.(jpg|png)/g, '.$1.webp');
	}

	if ( src.length > 0 ) {
		img.src = src;
	}
	if ( srcset.length > 0 ) {
		img.srcset = srcset;
	}
	if ( sizes.length > 0 ) {
		img.sizes = sizes;
	}
	img.addEventListener( 'load', blurpImgLoaded );

	element.append( img );
	element.classList.add( 'seen' );

}

function blurpScrolled() {

	if ( window.blurpScrollTimer === null ) {
		console.log ( 'scroll timer fired' );
		blurpSwapImages( blurpImages );

		window.blurpScrollTimer = true;
		
		window.setTimeout( function ( timer ) {
			window.blurpScrollTimer = null;
		}, 500, blurpScrollTimer );
	}

}

function blurpReady() {

	blurpImages = document.querySelectorAll( '.preloaded-background' );
	window.addEventListener( 'scroll', blurpScrolled );

	window.blurpScrollTimer = null;
	blurpScrolled();

}

function blurpLoad() {

	window.webpOk = false;

	var testImage = "UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA";
	var img = new Image();
	img.onload = function () {
		var result = (img.width > 0) && (img.height > 0);
		window.webpOk = result;
		blurpReady();
	};
	img.onerror = function () {
		window.webpOk = false;
		blurpReady();
	};
	img.src = "data:image/webp;base64," + testImage;

}

if( document.readyState === "complete" || document.readyState === "interactive" ) {

	blurpLoad();

} else {

	window.addEventListener( 'DOMContentLoaded', blurpLoad );

}
