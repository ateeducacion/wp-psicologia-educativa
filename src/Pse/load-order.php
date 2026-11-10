<?php
/**
 * Ordered load list for the modular PSE application.
 *
 * Single source of truth: `bootstrap.php` requires these files for tests and
 * the dev environment, and `build/pack-snippet.php` inlines them in this same
 * order into the Code Snippets bundle. A new file under `src/Pse/` must be
 * added here or the bundler fails.
 *
 * Paths are relative to this directory. Order matters: a class must come after
 * everything it extends or uses at load time, and `App.php` goes last. The
 * first entry has to start with its `namespace` statement: the bundler injects
 * the PSE_BUNDLE_LOADED guard right after it.
 *
 * @package Pse
 */

return array(
	'Domain/Vocabulary.php',
	'Domain/Stats.php',
	'Centre/CentreCatalogue.php',
	'Centre/CentreCatalogueSync.php',
	'Centre/CentreSettings.php',
	'Centre/Zones.php',
	'Access/Access.php',
	'PostType/Requests.php',
	'Notifications/Mailer.php',
	'PublicFront/Assets.php',
	'PublicFront/Actions.php',
	'PublicFront/Views.php',
	'PublicFront/Screen.php',
	'App.php',
);
