<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'emploi' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'CR2o%`,4DI,so~8Kg.LX>ok[C)jS@Ehv%#FEmrFoIOl~yA|2L>#0rq<W3`)V(N*:' );
define( 'SECURE_AUTH_KEY',  '!)XFBccCJx<2DB)3+C{-MA7ZU|P$t9$^D@fY+2U/@JsXIL`)97d`Ii_M5xs ;h_$' );
define( 'LOGGED_IN_KEY',    '5Z4El08C>HwC*-NZ*2X:XVhmYk|(b_~n[+2JH4jJ<;]5hc:<r[7Y-]KlY[epZvj|' );
define( 'NONCE_KEY',        '?R!g~{ SZlg[2eeoIm)Pe(LJ(q0E4K@T8Eb2Ft-xK1=kM56 a#Tu`[?[LhclNOZd' );
define( 'AUTH_SALT',        '@NEljcU.c[p:<186#[^P=.%^Z!$gX%j_/G,/^)a2*/uD ~L.C(12 UmiX+owE=-}' );
define( 'SECURE_AUTH_SALT', 'I8#&brjW~fY]4=/zndW?m$o4Pcl#u}oA*32Nsc-`t/D*k1qLm?|?N_&BPW JS0,A' );
define( 'LOGGED_IN_SALT',   ';x%3jv~ma!R}z9jF{6S%T{Szh;8n __9pOc(dlA#UL5.:EeI?uwNFw!++<!sf/yk' );
define( 'NONCE_SALT',       '/-la#`v0&kpA0&/Rd,Nu2GIsNihvSpg}(5mP6@iBYM.P^Sa+Z=YDd(14?W4%Ab9=' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
