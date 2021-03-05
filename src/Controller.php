<?php

namespace WP2StaticAdvancedCrawling;

class Controller {
    public function run() : void {
        add_filter(
            'wp2static_add_menu_items',
            [ self::class, 'addSubmenuPage' ]
        );

        add_filter(
            'wp2static_file_extensions_to_ignore',
            [ Detection::class, 'wp2staticFileExtensionsToIgnore' ],
            15,
            1
        );

        add_filter(
            'wp2static_filenames_to_ignore',
            [ Detection::class, 'wp2staticFilenamesToIgnore' ],
            15,
            1
        );

        add_filter(
            'wp2static_modify_initial_crawl_list',
            [ Detection::class, 'wp2staticModifyInitialCrawlList' ],
            15,
            1
        );

        add_action(
            'wp2static_process_html',
            [ Rewriter::class, 'rewrite' ],
            100,
            1
        );

        add_action(
            'wp2static_process_css',
            [ Rewriter::class, 'rewrite' ],
            100,
            1
        );

        add_action(
            'wp2static_process_js',
            [ Rewriter::class, 'rewrite' ],
            100,
            1
        );

        add_action(
            'wp2static_process_robots_txt',
            [ Rewriter::class, 'rewrite' ],
            100,
            1
        );

        add_action(
            'wp2static_process_xml',
            [ Rewriter::class, 'rewrite' ],
            100,
            1
        );

        add_action(
            'admin_post_wp2static_advanced_crawling_save_options',
            [ $this, 'saveOptionsFromUI' ],
            15,
            1
        );

        add_action(
            'admin_menu',
            [ $this, 'addOptionsPage' ],
            15,
            1
        );

        add_action(
            'pre_post_update',
            [ $this, 'prePostUpdateHandler' ],
            0,
            1
        );

        add_action(
            'wp2static_crawl',
            [ Crawler::class, 'wp2staticCrawl' ],
            15,
            2
        );

        add_action(
            'wp2static_detect',
            [ Detection::class, 'wp2staticDetect' ],
            15,
            2
        );

        do_action(
            'wp2static_register_addon',
            'wp2static-addon-advanced-crawling',
            'crawl',
            'Advanced Crawling',
            'https://github.com/WP2Static/wp2static-addon-advanced-crawling',
            'Provides advanced crawling options'
        );
    }

    public static function createOptionsTable() : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_addon_advanced_crawling_options';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            value VARCHAR(255) NOT NULL,
            label VARCHAR(255) NULL,
            description VARCHAR(255) NULL,
            blob_value BLOB NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        \WP2Static\Controller::ensureIndex(
            $table_name,
            'name',
            "CREATE UNIQUE INDEX name ON $table_name (name)"
        );
    }

    public static function addURLQueueCrawledTime() : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_urls';

        $row = $wpdb->get_row( "SHOW COLUMNS FROM $table_name WHERE Field = 'crawled_time'" );
        if ( ! $row ) {
            $wpdb->query( "ALTER TABLE $table_name ADD COLUMN crawled_time DATETIME" );
        }

        \WP2Static\Controller::ensureIndex(
            $table_name,
            'crawled_time',
            "CREATE INDEX crawled_time ON $table_name (crawled_time)"
        );
    }

    /**
     *  Get all add-on options
     *
     *  @return mixed[] All options
     */
    public static function getOptions() : array {
        global $wpdb;
        $options = [];

        $table_name = $wpdb->prefix . 'wp2static_addon_advanced_crawling_options';

        $rows = $wpdb->get_results( "SELECT * FROM $table_name" );

        foreach ( $rows as $row ) {
            $options[ $row->name ] = $row;
        }

        return $options;
    }

    /**
     * Seed options
     */
    public static function seedOptions() : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_addon_advanced_crawling_options';

        $queries = [];

        $query_string =
            "INSERT IGNORE INTO $table_name (name, value, label, description) " .
            'VALUES (%s, %s, %s, %s);';

        $queries[] = $wpdb->prepare(
            $query_string,
            'addURLsWhileCrawling',
            '1',
            'Add URLs Discovered While Crawling',
            ''
        );

        $queries[] = $wpdb->prepare(
            $query_string,
            'crawlChunkSize',
            '20',
            'Crawl Chunk Size',
            'The maximum number of URLs to crawl in one batch.'
        );

        $queries[] = $wpdb->prepare(
            $query_string,
            'crawlOnlyChangedURLs',
            '0',
            'Crawl Only Changed URLs',
            ''
        );

        $queries[] = $wpdb->prepare(
            $query_string,
            'crawlProgressReportInterval',
            '300',
            'Crawl Progress Report Interval',
            'Report crawl progress after this many URLs are crawled.'
        );

        $queries[] = $wpdb->prepare(
            $query_string,
            'crawlSitemaps',
            '1',
            'Crawl Sitemaps',
            ''
        );

        $queries[] = $wpdb->prepare(
            $query_string,
            'detectRedirectionPluginURLs',
            '1',
            'Detect Redirects from the <a href="https://redirection.me/">Redirection Plugin</a>',
            ''
        );

        $blob_query_string =
            "INSERT IGNORE INTO $table_name (name, value, label, description, blob_value) " .
            'VALUES (%s, %s, %s, %s, %s);';

        $queries[] = $wpdb->prepare(
            $blob_query_string,
            'additionalHostsToRewrite',
            '1',
            'Additional Hosts to Rewrite',
            '',
            ''
        );

        $queries[] = $wpdb->prepare(
            $blob_query_string,
            'additionalPathsToCrawl',
            '1',
            'Additional Paths to Crawl',
            '',
            ''
        );

        $queries[] = $wpdb->prepare(
            $blob_query_string,
            'filenamesToIgnore',
            '1',
            'Filenames to Ignore',
            '',
            // @codingStandardsIgnoreStart
            "__MACOSX\n.babelrc\n.git\n.gitignore\n.gitkeep\n.htaccess\n.php\n.svn\n.travis.yml\nbackwpup\nbower_components\nbower.json\ncomposer.json\ncomposer.lock\nconfig.rb\ncurrent-export\nDockerfile\ngulpfile.js\nlatest-export\nLICENSE\nMakefile\nnode_modules\npackage.json\npb_backupbuddy\nplugins/wp2static\nprevious-export\nREADME\nstatic-html-output-plugin\n/tests/\nthumbs.db\ntinymce\nwc-logs\nwpallexport\nwpallimport\nwp-static-html-output\nwp2static-addon\nwp2static-crawled-site\nwp2static-processed-site\nwp2static-working-files\nyarn-error.log\nyarn.lock"
            // @codingStandardsIgnoreEnd
        );

        $queries[] = $wpdb->prepare(
            $blob_query_string,
            'fileExtensionsToIgnore',
            '1',
            'File Extensions to Ignore',
            '',
            // @codingStandardsIgnoreStart
            ".bat\n.crt\n.DS_Store\n.git\n.idea\n.ini\n.less\n.map\n.md\n.mo\n.php\n.PHP\n.phtml\n.po\n.pot\n.scss\n.sh\n.sql\n.SQL\n.tar.gz\n.tpl\n.txt\n.yarn\n.zip"
            // @codingStandardsIgnoreEnd
        );

        $wpdb->query( 'START TRANSACTION' );
        foreach ( $queries as $query ) {
            $wpdb->query( $query );
        }
        $wpdb->query( 'COMMIT' );
    }

    public static function activateForSingleSite(): void {
        self::createOptionsTable();
        self::seedOptions();
        self::addURLQueueCrawledTime();
    }

    public static function activate( bool $network_wide = null ) : void {
        if ( $network_wide ) {
            global $wpdb;

            $query = 'SELECT blog_id FROM %s WHERE site_id = %d;';

            $site_ids = $wpdb->get_col(
                sprintf(
                    $query,
                    $wpdb->blogs,
                    $wpdb->siteid
                )
            );

            foreach ( $site_ids as $site_id ) {
                switch_to_blog( $site_id );
                self::activateForSingleSite();
            }

            restore_current_blog();
        } else {
            self::activateForSingleSite();
        }
    }

    public static function deactivate( bool $network_wide = null ) : void {
    }

    /**
     * Save options
     *
     * @param mixed $value option value to save
     */
    public static function saveOption( string $name, $value ) : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_addon_advanced_crawling_options';

        $query_string = "INSERT INTO $table_name (name, value) VALUES (%s, %s);";
        $query = $wpdb->prepare( $query_string, $name, $value );

        $wpdb->query( $query );
    }

    public static function renderOptionsPage() : void {
        self::activateForSingleSite();

        $view = [];
        $view['nonce_action'] = 'wp2static-advanced-crawling-options';
        $view['options'] = self::getOptions();

        require_once __DIR__ . '/../views/options-page.php';
    }

    /**
     * Add WP2Static submenu
     *
     * @param mixed[] $submenu_pages array of submenu pages
     * @return mixed[] array of submenu pages
     */
    public static function addSubmenuPage( array $submenu_pages ) : array {
        $submenu_pages['advanced-crawling'] = [
            'WP2StaticAdvancedCrawling\Controller',
            'renderOptionsPage',
        ];

        return $submenu_pages;
    }

    public static function saveOptionsFromUI() : void {
        check_admin_referer( 'wp2static-advanced-crawling-options' );

        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_addon_advanced_crawling_options';

        $wpdb->update(
            $table_name,
            [ 'blob_value' => $_POST['additionalHostsToRewrite'] ],
            [ 'name' => 'additionalHostsToRewrite' ]
        );

        $wpdb->update(
            $table_name,
            [ 'blob_value' => $_POST['additionalPathsToCrawl'] ],
            [ 'name' => 'additionalPathsToCrawl' ]
        );

        $wpdb->update(
            $table_name,
            [ 'value' => isset( $_POST['addURLsWhileCrawling'] ) ? 1 : 0 ],
            [ 'name' => 'addURLsWhileCrawling' ]
        );

        $wpdb->update(
            $table_name,
            [ 'value' => intval( $_POST['crawlChunkSize'] ) ],
            [ 'name' => 'crawlChunkSize' ]
        );

        $wpdb->update(
            $table_name,
            [ 'value' => isset( $_POST['crawlOnlyChangedURLs'] ) ? 1 : 0 ],
            [ 'name' => 'crawlOnlyChangedURLs' ]
        );

        $wpdb->update(
            $table_name,
            [ 'value' => intval( $_POST['crawlProgressReportInterval'] ) ],
            [ 'name' => 'crawlProgressReportInterval' ]
        );

        $wpdb->update(
            $table_name,
            [ 'value' => isset( $_POST['crawlSitemaps'] ) ? 1 : 0 ],
            [ 'name' => 'crawlSitemaps' ]
        );

        $wpdb->update(
            $table_name,
            [ 'value' => isset( $_POST['detectRedirectionPluginURLs'] ) ? 1 : 0 ],
            [ 'name' => 'detectRedirectionPluginURLs' ]
        );

        $wpdb->update(
            $table_name,
            [ 'blob_value' => $_POST['filenamesToIgnore'] ],
            [ 'name' => 'filenamesToIgnore' ]
        );

        $wpdb->update(
            $table_name,
            [ 'blob_value' => $_POST['fileExtensionsToIgnore'] ],
            [ 'name' => 'fileExtensionsToIgnore' ]
        );

        wp_safe_redirect( admin_url( 'admin.php?page=wp2static-addon-advanced-crawling' ) );
        exit;
    }

    /**
     * Get option BLOB value
     *
     * @return string option BLOB value
     */
    public static function getBlobValue( string $name ) : string {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_addon_advanced_crawling_options';

        $sql = $wpdb->prepare(
            "SELECT blob_value FROM $table_name WHERE" . ' name = %s LIMIT 1',
            $name
        );

        $option_value = $wpdb->get_var( $sql );

        if ( ! is_string( $option_value ) ) {
            return '';
        }

        return $option_value;
    }

    /**
     * @return array<string>
     */
    public static function getLineDelimitedBlobValue( string $name ) : array {
        $vals = preg_split(
            '/\r\n|\r|\n/',
            self::getBlobValue( $name )
        );

        if ( ! $vals ) {
            return [];
        }

        return $vals;
    }

    /**
     * Get option value
     *
     * @return string option value
     */
    public static function getValue( string $name ) : string {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_addon_advanced_crawling_options';

        $sql = $wpdb->prepare(
            "SELECT value FROM $table_name WHERE" . ' name = %s LIMIT 1',
            $name
        );

        $option_value = $wpdb->get_var( $sql );

        if ( ! is_string( $option_value ) ) {
            return '';
        }

        return $option_value;
    }

    public function addOptionsPage() : void {
        add_submenu_page(
            '',
            'Advanced Crawling Options',
            'Advanced Crawling Options',
            'manage_options',
            'wp2static-addon-advanced-crawling',
            [ $this, 'renderOptionsPage' ]
        );
    }

    public static function dbNow() : string {
        global $wpdb;

        return $wpdb->get_col( 'SELECT NOW()' )[0];
    }

    public static function prePostUpdateHandler( int $post_id ) : void {
        $permalink = get_permalink( $post_id );
        if ( $permalink ) {
            $post_url = wp_make_link_relative( $permalink );
            CrawlQueue::clearCrawledTime( $post_url );
        }
    }
}
