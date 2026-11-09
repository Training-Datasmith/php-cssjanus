[![Packagist](https://img.shields.io/packagist/v/wikimedia/cssjanus.svg?style=flat)](https://packagist.org/packages/wikimedia/cssjanus)

# Css_Janus

Convert CSS stylesheets between left-to-right and right-to-left.

## Usage

```php
Css_Janus::transform( string $css, array $options = [] ) : string
```

Parameters;

* ``$css`` (string) Stylesheet to transform.
* ``$options`` (array) Optional keys ``transformDirInUrl`` and ``transformEdgeInUrl`` for URL flipping.

Example:

```php
$rtl_css = Css_Janus::transform( $ltr_css );
```

### Preventing flipping

If a rule is not meant to be flipped by Css_Janus, use a `/* @noflip */` comment to protect the rule.

```css
.rule1 {
  /* Will be converted to margin-right */
  margin-left: 1em;
}
/* @noflip */
.rule2 {
  /* Will be preserved as margin-left */
  margin-left: 1em;
}
```

## CSS Logical Properties

We encourage and recommend use of [CSS logical properties](https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_logical_properties_and_values) for the subset of CSS features where a native direction-aware version of a CSS property exists (be sure to check [browser support](https://caniuse.com/) for specific properties).
You can, for example, set properties like `margin-inline-start` instead of `margin-left`, which the browser flips based on content direction, and work seamlessly alongside other CSS properties that Css_Janus flips instead.

Note that CSS logical properties flip based on nearest content direction and content language, whereas Css_Janus is generally configured to flip by user language and UI direction.

## Port

The upstream Css_Janus project was originally a [Google project](http://code.google.com/p/cssjanus/) created by Lindsey Simon in 2008, written in Python. It was ported to PHP by Roan Kattouw in 2010 for use in MediaWiki, and ported to Node.js by Trevor Parscal in 2012.

As of 2014, the canonical specification and reference implementation is [node-cssjanus](https://gerrit.wikimedia.org/g/mediawiki/libs/node-cssjanus), which is then ported to PHP after each release.

## Contribute

* Issue tracker: <https://phabricator.wikimedia.org/tag/cssjanus/>
* Source code: <https://gerrit.wikimedia.org/g/mediawiki/libs/php-cssjanus>
* Submit patches via Gerrit: <https://www.mediawiki.org/wiki/Developer_account>
