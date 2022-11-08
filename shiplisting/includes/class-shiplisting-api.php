<?php

class Shiplisting_Api
{
    protected $api_host;
    public $api_uri;
    protected $api_key;
    protected $api_curl_timeout = 5;
    protected $site_id;
    protected $language;
    protected $imageSlider_width = 320;
    protected $imageSlider_height = 320;

    protected $api_source;

    // https://api.yachtall.com/de/docu/optional-variables/
    protected $optional_variables = [
        'trans'   => [0, 1],
        'bnr',
        'pg',
        'sort', //todo
        'asc'     => ['asc', 'desc'],
        'q',
        'bid'     => [],
        'not_bid' => [],
        'uid',
        'btid'    => [1, 2, 3, 4, 5],
        'bcid', //todo
        'bm',
        'manfb', //todo
        'lngf',
        'lngt',
        'cabf',
        'cabt',
        'persf',
        'perst',
        'ybf',
        'ybt',
        'powf',
        'powt',
        'engf',
        'engt',
        'fuel', //todo
        'ppid', //todo
        'hmid', //todo
        'sailf',
        'sailt',
        'fly'     => [0, 1],
        'ct', //todo
        'curr',
        'usstate', //todo
        'newused' => [0, 1],
        'bclass'  => [1, 2, 3, 4, 5],
        'sprcf',
        'sprct',
        'cdid', //todo
    ];
    public $translation;

    public $currentRoute;

    public function __construct()
    {
        $this->init();
    }

    public function init()
    {
        $this->api_host = 'https://api.yachtall.com/';
        $this->api_uri  = $this->api_host . '[LANG]/[URL_PART1]/[URL_PART2]/?code=[API_KEY]&site_id=[SITE_ID]&bt_vers=3.0&api_vers=1.0'
                          . '&ip=' . $_SERVER['REMOTE_ADDR'] . '&http_agent=' . self::escapeUrlForApi($_SERVER['HTTP_USER_AGENT'])
                          . '&remote_host=' . gethostbyaddr($_SERVER['REMOTE_ADDR']);
        $this->api_key  = get_option('shiplisting_api_key');
        $this->site_id  = get_option('shiplisting_api_siteid');

        //todo all other languages
        if (get_locale() == 'de_DE') {
            $this->language = 'de';
        } else {
            $this->language = 'en';
        }

        if (empty($this->api_key) || empty($this->site_id)) {
            return false;
        }

        if ( ! $this->translation) {
            $this->get_translation();
        }
    }

    // special encoding for Yachtino API
    public static function escapeUrlForApi($var)
    {
        $var = str_replace('/', '~~', $var);
        $var = str_replace('\\', '§~§', $var);
        $var = rawurlencode($var);

        return $var;
    }

    public function update_uri($host)
    {
        $this->api_host = $host;
        $this->api_uri  = $this->api_host . '[LANG]/[URL_PART1]/[URL_PART2]/?code=[API_KEY]&site_id=[SITE_ID]&bt_vers=3.0&api_vers=1.0'
                          . '&ip=' . $_SERVER['REMOTE_ADDR'] . '&http_agent=' . self::escapeUrlForApi($_SERVER['HTTP_USER_AGENT'])
                          . '&remote_host=' . gethostbyaddr($_SERVER['REMOTE_ADDR']);
    }

    public function check_service()
    {
        $curlInit = curl_init($this->api_host);
        curl_setopt($curlInit, CURLOPT_CONNECTTIMEOUT, $this->api_curl_timeout);
        curl_setopt($curlInit, CURLOPT_HEADER, true);
        curl_setopt($curlInit, CURLOPT_NOBODY, true);
        curl_setopt($curlInit, CURLOPT_RETURNTRANSFER, true);

        //get answer
        $response = curl_exec($curlInit);

        curl_close($curlInit);
        if ($response) {
            return true;
        }

        return false;
    }

    public function send_request($urlpart1, $urlpart2, $fields = '', $data = null)
    {
        global $wpdb;

        if ( ! $this->check_service()) {
            //todo: handle error
            return;
        }

        if ( ! $this->api_key) {
            //todo: handle error
            return;
        }

        if ( ! $this->site_id) {
            //todo: handle error
            return;
        }

        $lang = @Shiplisting_Shortcodes::$api->currentRoute['language'];
        if (empty($lang)) {
            $lang = $this->language;
        }

        try {

            $tmpUri = $this->api_uri;
            $tmpUri = str_replace('[LANG]', $lang, $tmpUri);
            $tmpUri = str_replace('[URL_PART1]', $urlpart1, $tmpUri);
            $tmpUri = str_replace('[URL_PART2]', $urlpart2, $tmpUri);
            $tmpUri = str_replace('[API_KEY]', $this->api_key, $tmpUri);
            $tmpUri = str_replace('[SITE_ID]', $this->site_id, $tmpUri);
            if ( ! empty($fields)) {
                $tmpUri .= $fields;
            }

            // caching?
            $do_caching = true;
            $cache_hash = hash('sha512', json_encode([
                md5($tmpUri),
                $data
            ]));

            $plugin_settings = $this->get_plugin_settings();

            // if cache hash is present
            if (($plugin_settings->{'cache_active'} == 1) && $cache_hash && ($data == null)) {
                if ($wpdb) {
                    $cache_result = $wpdb->get_results("SELECT * FROM `wp_shiplisting_caching` WHERE `hash` = '$cache_hash'");
                    if ($cache_result && sizeof($cache_result) > 0) {
                        $do_caching = false;

                        date_default_timezone_set('Europe/Berlin');

                        $used           = $cache_result[0]->{'used'};
                        $cache_response = $cache_result[0]->{'result'};
                        $added          = new DateTime($cache_result[0]->{'added'});
                        $added          = $added->modify('+' . $plugin_settings->{'cache_time'});
                        $now            = new DateTime();

                        if ($now->diff($added)->invert == 1) {
                            $wpdb->delete('wp_shiplisting_caching', [
                                'hash' => $cache_hash
                            ]);
                            $do_caching = true;
                        } else {
                            $wpdb->update('wp_shiplisting_caching', [
                                'used' => ($used + 1)
                            ], [
                                'id' => $cache_result[0]->{'id'}
                            ]);

                            return base64_decode($cache_response);
                        }
                    }
                }
            }

            $curlInit = curl_init($tmpUri);

            curl_setopt($curlInit, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curlInit, CURLOPT_TIMEOUT, $this->api_curl_timeout);
            curl_setopt($curlInit, CURLOPT_CONNECTTIMEOUT, $this->api_curl_timeout);
            curl_setopt($curlInit, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);

            if ($data != null) {
                curl_setopt($curlInit, CURLOPT_URL, $tmpUri);
                curl_setopt($curlInit, CURLOPT_POST, 1);
                curl_setopt($curlInit, CURLOPT_POSTFIELDS, http_build_query($data));
            } else {
                curl_setopt($curlInit, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                ));
            }

            $result = curl_exec($curlInit);
            curl_close($curlInit);

            // add caching
            if (($plugin_settings->{'cache_active'} == 1) && $do_caching && ($data == null)) {
                $wpdb->insert('wp_shiplisting_caching', [
                    'hash'   => $cache_hash,
                    'result' => base64_encode($result),
                    'used'   => 1
                ]);
            }
        } catch (\Exception $e) {
            //todo:: handle error
            error_log($e, 0);
            throw new \Exception();
        }

        return $result;
    }

    public function get_translation()
    {
        $this->translation = json_decode($this->send_request('translation', 'translate'), true)["translation"];
    }

    public function ajax_get_translation()
    {
        return json_decode($this->send_request('translation', 'translate', '&eur_curr=1'), true)["translation"];
    }

    public function ajax_get_customer_form_data($filter = '')
    {
        $tmpFormData = json_decode($this->send_request('request', 'partner-forms', $filter), true);
        if ( ! $tmpFormData) {
            return;
        }

        return $tmpFormData;
    }

    public function ajax_get_boats($filter = '')
    {
        if (is_admin()) {
            return;
        }

        $tmpBoat = json_decode($this->send_request('boat-data', 'boats', $filter), true);
        // if ( !$tmpBoat ) {
        // return;
        // }

        // if (!$tmpBoat[ 'adverts' ]) {
        // return;
        // }

        // if (sizeof($tmpBoat[ 'adverts' ]) <= 0) {
        // return;
        // }

        $boats = $tmpBoat;
        // if (!$boats['adverts'])
        // return;

        wp_enqueue_script("shiplisting_public", plugin_dir_url(__DIR__) . 'public/js/shiplisting-public.js',
            array('jquery'), SHIPLISTING_VERSION, false);
        wp_localize_script("shiplisting_public", 'shiplisting_public',
            array('ajax_url' => admin_url('admin-ajax.php')));

        if (empty($tmpBoat['adverts'])) {
            return;
        } else {
            return $tmpBoat;
        }
    }

    public function get_boats($filter = '')
    {
        if (is_admin()) {
            return;
        }

        $tmpBoats = json_decode($this->send_request('boat-data', 'boats', 'bclass=2'), true);
        if ( ! $tmpBoats) {
            return;
        }

        $adverts = $tmpBoats['adverts'];
        if ($adverts) {
            foreach ($adverts as $advert) {
                $boatId = $advert['attr']['code'];

                $general_boat_model   = $advert['val']['boat_data']['general_data']['boat_model']['val'];
                $general_boat_picture = '';
                if (sizeof($advert['val']['pictures']) > 1) {
                    //todo: sort after order when more pictures avaib
                    $tmpSortArr = array();
                    foreach ($advert['val']['pictures'] as $picture) {
                        array_push($tmpSortArr, $picture);
                    }
                    usort($tmpSortArr, function ($a, $b) {
                        return $a['attr']['order'] > $b['attr']['order'];
                    });
                } else {
                    $general_boat_picture = $advert['val']['pictures'][0]['val'];
                }
                $general_boat_year_built = $advert['val']['boat_data']['general_data']['year_built']['val'];

                $measure_boat_loa       = $advert['val']['boat_data']['measure']['loa']['val'] . " " . $advert['val']['boat_data']['measure']['loa']['attr']['unit'];
                $measure_boat_beam      = $advert['val']['boat_data']['measure']['beam']['val'] . " " . $advert['val']['boat_data']['measure']['beam']['attr']['unit'];
                $sale_price_amount      = $advert['val']['sale_data']['price']['attr']['currency_sign'] . " " . $advert['val']['sale_data']['price']['val']['price_amount']['val'];
                $sale_price_euro_amount = "€ " . $advert['val']['sale_data']['price']['val']['euro_amount']['val'];
                $sale_location          = $advert['val']['sale_data']['location']['attr']['country'];
                $managing_owner         = $advert['val']['managing']['owner']['val'];
                $managing_owner_added   = $advert['val']['managing']['added'];

                echo '<img src="' . $general_boat_picture . '" width="320" height="320" /><br>' . $general_boat_model . " - " . $sale_price_euro_amount . " - " . $measure_boat_loa . " x " . $measure_boat_beam . " - " . $sale_location . " - " . $managing_owner . " - " . $managing_owner_added . "<br>";
            }
        }
    }

    public function get_boat()
    {
        if (is_admin()) {
            return;
        }

        echo '<script type="text/javascript" src="' . get_bloginfo('url') . '/wp-content/plugins/shiplisting/public/js/shiplisting-public.js"></script>';
    }

    public function ajax_get_boat($bid)
    {
        if (is_admin()) {
            return;
        }

        $tmpBoat = json_decode($this->send_request('boat-data', 'boat', '&bid=' . $bid . '&trans=1'), true);
        if ( ! $tmpBoat) {
            return;
        }

        $countries = $this->ajax_get_customer_form_data('&array=1&kind=request');
        if ( ! $countries) {
            return;
        }

        $boat_data = $tmpBoat['adverts'][0]['val'];
        if ( ! $boat_data) {
            return;
        }

        $this->translation = $tmpBoat['translation'];
        if ( ! $this->translation) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            die($this->send_request('request', 'send-request', '', $_POST));
        }

        if (sizeof($boat_data['pictures']) > 0) {
            $sizePics = sizeof(@$boat_data['pictures']);
            $sizeVids = sizeof(@$boat_data['videos']);
            $sizePdf  = sizeof(@$boat_data['pdf']);
            $start    = 1;

            if ($sizeVids > 0) {
                $start += $sizeVids;
            }
            if ($sizePdf > 0) {
                $start += $sizePdf;
            }

            $boat_data["imageSlider"]["html"] = '
            <div class="shiplisting-images-current rounded-corners">
                <img src="' . $boat_data['pictures'][0]['val'] . '" width="' . $this->imageSlider_width . '" height="' . $this->imageSlider_height . '" />
            </div>
            <div class="shiplisting-images-position">' . $start . ' / ' . ($sizePics + $sizeVids + $sizePdf) . '</div>
            <div class="shiplisting-images-slider-wrapper rounded-corners">
                <ul class="shiplisting-images-slider">';

            if (sizeof($boat_data['videos']) > 0) {
                foreach ($boat_data['videos'] as $key => $video) {
                    $videoUrl = $video['val'];
                    if (stripos($videoUrl, 'youtube') > 0) {
                        $videoPicture = 'https://img.youtube.com/vi/{video_id}/maxresdefault.jpg';
                        $videoId      = substr($videoUrl, stripos($videoUrl, 'embed/') + 6);
                        $videoId      = substr($videoId, 0, stripos($videoId, '?'));

                        if ( ! empty($videoId)) {
                            $videoPicture                     = str_replace("{video_id}", $videoId, $videoPicture);
                            $boat_data["imageSlider"]["html"] .= '<li class="shiplisting-images-slider-item video" video-id="' . $videoId . '"><img src="' . $videoPicture . '" /><div class="shiplisting-video-thumb">VIDEO</div></li>';
                        }
                    }
                }
            }

            if (sizeof($boat_data['pdf']) > 0) {
                foreach ($boat_data['pdf'] as $key => $pdf) {
                    $pdfUrl                           = $pdf['val'];
                    $boat_data["imageSlider"]["html"] .= '<li class="shiplisting-images-slider-item pdf" pdf-url="' . $pdfUrl . '"><div class="shiplisting-pdf-thumb">PDF</div></li>';
                }
            }

            foreach ($boat_data['pictures'] as $key => $picture) {
                if ($key == 0) {
                    $current = ' current';
                } else {
                    $current = '';
                }

                $boat_data["imageSlider"]["html"] .= '<li class="shiplisting-images-slider-item' . $current . '"><img src="' . str_replace('huge_',
                        'list_', $picture['val']) . '" /></li>';
            }
            $boat_data["imageSlider"]["html"] .= '
                </ul>
            </div>
            ';
        }

        if (!empty($boat_data['sale_data'])) {
            if (!empty($boat_data['sale_data']['price']['val']['old_price']['val'])) {
                $boat_data['price_details']['html'] = '<span class="shiplisting-old-price">' . $boat_data['sale_data']['price']['attr']['currency_sign'] . ' ' . $boat_data['sale_data']['price']['val']['old_price']['val'] . '</span> ';
                $boat_data['price_details']['html'] .= $boat_data['sale_data']['price']['attr']['currency_sign'] . ' ' . $boat_data['sale_data']['price']['val']['price_amount']['val'];

                if ( ! empty($boat_data['sale_data']['price']['val']['euro_amount']['val'])) {
                    $boat_data['price_details']['html'] .= '<br>(≈ € ' . $boat_data['sale_data']['price']['val']['euro_amount']['val'] . ')';
                }
            } else {
                $boat_data['price_details']['html'] = $boat_data['sale_data']['price']['attr']['currency_sign'] . ' ' . $boat_data['sale_data']['price']['val']['price_amount']['val'];

                if ( ! empty($boat_data['sale_data']['price']['val']['euro_amount']['val'])) {
                    $boat_data['price_details']['html'] .= ' (≈ € ' . $boat_data['sale_data']['price']['val']['euro_amount']['val'] . ')';
                }
            }

            if ( ! empty($boat_data['sale_data']['price']['val']['vat_included']['val']) || ! empty($boat_data['sale_data']['price']['val']['eu_tax']['val'])) {
                $steuer = '<br><small>';
                if ( ! empty($boat_data['sale_data']['price']['val']['vat_included']['val'])) {
                    $steuer .= $boat_data['sale_data']['price']['val']['vat_included']['val'];
                }

                if ( ! empty($boat_data['sale_data']['price']['val']['vat_included']['val']) && ! empty($boat_data['sale_data']['price']['val']['eu_tax']['val'])) {
                    $steuer .= ', ';
                }

                if ( ! empty($boat_data['sale_data']['price']['val']['eu_tax']['val'])) {
                    $steuer .= $boat_data['sale_data']['price']['val']['eu_tax']['val'];
                }

                $steuer                             .= '</small>';
                $boat_data['price_details']['html'] .= $steuer;
            }

            if ( ! empty($boat_data['sale_data']['price']['val']['plus_broker_fee']['val']) || ! empty($boat_data['sale_data']['price']['val']['price_negotiable']['val'])) {
                $makler = '<br><small>';

                if ( ! empty($boat_data['sale_data']['price']['val']['plus_broker_fee']['val'])) {
                    $makler .= $boat_data['sale_data']['price']['val']['plus_broker_fee']['val'];
                }

                if ( ! empty($boat_data['sale_data']['price']['val']['plus_broker_fee']['val']) && ! empty($boat_data['sale_data']['price']['val']['price_negotiable']['val'])) {
                    $makler .= ', ';
                }

                if ( ! empty($boat_data['sale_data']['price']['val']['price_negotiable']['val'])) {
                    $makler .= $boat_data['sale_data']['price']['val']['price_negotiable']['val'];
                }

                $makler                             .= '</small>';
                $boat_data['price_details']['html'] .= $makler;
            }

            if ( ! empty($boat_data['sale_data']['under_offer']['val'])) {
                $boat_data['price_details']['html'] .= '<br><span class="highlight bold">' . $this->translation['boat']['sale_data']['under_offer'] . '</span>';
            }

            if (!empty($boat_data['sale_data']['price']['val']['trade_in']['val'])
            && ($boat_data['sale_data']['price']['val']['trade_in']['val'] == 'ja' || $boat_data['sale_data']['price']['val']['trade_in']['val'] == 'yes')) {
                $boat_data['sale_data']['price']['val']['trade_in']['val'] = '<span class="bold">' . $this->translation['boat']['sale_data']['price']['trade_in'] . '</span>';
            } else {
                $boat_data['sale_data']['price']['val']['trade_in']['val'] = '';
            }

            if ($boat_data['sale_data']['was_in_charter']['val'] == 'ja') {
                $boat_data['sale_data']['was_in_charter']['val'] = '(' . $this->translation['boat']['sale_data']['was_in_charter'] . ')';
            }

        }

        // build beam draft
        if ( ! empty($boat_data['boat_data']['measure']['beam']['val'])) {
            $boat_data['other_details']['beamdraft']['html'] .= $boat_data['boat_data']['measure']['beam']['val'] . ' ' . $boat_data['boat_data']['measure']['beam']['attr']['unit'];
        }
        if ( ! empty($boat_data['boat_data']['measure']['beam']['val']) && ! empty($boat_data['boat_data']['measure']['draft']['val'])) {
            $boat_data['other_details']['beamdraft']['html'] .= ' / ';
        }
        if ( ! empty($boat_data['boat_data']['measure']['draft']['val'])) {
            $boat_data['other_details']['beamdraft']['html'] .= $boat_data['boat_data']['measure']['draft']['val'] . ' ' . $boat_data['boat_data']['measure']['draft']['attr']['unit'];
        }
        if ( ! empty($boat_data['boat_data']['measure']['draft_to']['val'])) {
            $boat_data['other_details']['beamdraft']['html'] .= ' - ' . $boat_data['boat_data']['measure']['draft_to']['val'] . ' ' . $boat_data['boat_data']['measure']['draft_to']['attr']['unit'];
        }

        // build contact data
        $contactHtml = '';
        if ( ! empty($tmpBoat['offices']['office']['val']['logo']['val'])) {
            $contactHtml .= '<img src="' . $tmpBoat['offices']['office']['val']['logo']['val'] . '"';
            if ( ! empty($tmpBoat['offices']['office']['val']['logo']['attr']['width']) && ! empty($tmpBoat['offices']['office']['val']['logo']['attr']['height'])) {
                $contactHtml .= ' width="' . $tmpBoat['offices']['office']['val']['logo']['attr']['width'] . '" height="' . $tmpBoat['offices']['office']['val']['logo']['attr']['height'] . '"';
            }
            $contactHtml .= ' />';
        }

        $contactHtml .= '
                <div class="shiplisting-sale-details-row">
                    <div class="shiplisting-sale-details-label">' . $this->translation['offices']['contact'] . ':</div>
                    <div class="shiplisting-sale-details-field">';

        if ( ! empty($tmpBoat['offices']['office']['val']['office_name']['val'])) {
            $contactHtml .= '<b>' . $tmpBoat['offices']['office']['val']['office_name']['val'] . '</b><br>';
        }

        if (is_array($tmpBoat['offices']['office']['val']['contact_person']['val'])) {
            if ( ! empty($tmpBoat['offices']['office']['val']['contact_person']['val']['title']['val'])) {
                $contactHtml .= $tmpBoat['offices']['office']['val']['contact_person']['val']['title']['val'] . ' ';
            }

            if ( ! empty($tmpBoat['offices']['office']['val']['contact_person']['val']['firstname']['val'])) {
                $contactHtml .= $tmpBoat['offices']['office']['val']['contact_person']['val']['firstname']['val'] . ' ';
            }

            if ( ! empty($tmpBoat['offices']['office']['val']['contact_person']['val']['surname']['val'])) {
                $contactHtml .= $tmpBoat['offices']['office']['val']['contact_person']['val']['surname']['val'] . ' ';
            }

            $contactHtml .= '<br>';
        }

        if ( ! empty($tmpBoat['offices']['office']['val']['address']['val'])) {
            $contactHtml .= $tmpBoat['offices']['office']['val']['address']['val'] . '<br>';
        }

        if ( ! empty($tmpBoat['offices']['office']['val']['postcode']['val']) && ! empty($tmpBoat['offices']['office']['val']['city']['val'])) {
            $contactHtml .= $tmpBoat['offices']['office']['val']['postcode']['val'] . ' ' . $tmpBoat['offices']['office']['val']['city']['val'] . '<br>';
        }

        if ( ! empty($tmpBoat['offices']['office']['val']['country']['val'])) {
            $contactHtml .= $tmpBoat['offices']['office']['val']['country']['val'];
        }

        $contactHtml .= '
                    </div>
                </div>';

        if ( ! empty($tmpBoat['offices']['office']['val']['phone1']['val'])) {
            $contactHtml .= '
                <div class="shiplisting-sale-details-row">
                    <div class="shiplisting-sale-details-label">' . $this->translation['offices']['phone1'] . ':</div>
                    <div class="shiplisting-sale-details-field">' . $tmpBoat['offices']['office']['val']['phone1']['val'] . '</div>
                </div>';
        }

        if ( ! empty($tmpBoat['offices']['office']['val']['phone2']['val'])) {
            $contactHtml .= '
                <div class="shiplisting-sale-details-row">
                    <div class="shiplisting-sale-details-label">' . $this->translation['offices']['phone2'] . ':</div>
                    <div class="shiplisting-sale-details-field">' . $tmpBoat['offices']['office']['val']['phone2']['val'] . '</div>
                </div>';
        }

        if ( ! empty($tmpBoat['offices']['office']['val']['mobile1']['val'])) {
            $contactHtml .= '
                <div class="shiplisting-sale-details-row">
                    <div class="shiplisting-sale-details-label">' . $this->translation['offices']['mobile1'] . ':</div>
                    <div class="shiplisting-sale-details-field">' . $tmpBoat['offices']['office']['val']['mobile1']['val'] . '</div>
                </div>';
        }

        if ( ! empty($tmpBoat['offices']['office']['val']['fax1']['val'])) {
            $contactHtml .= '
                <div class="shiplisting-sale-details-row">
                    <div class="shiplisting-sale-details-label">' . $this->translation['offices']['fax1'] . ':</div>
                    <div class="shiplisting-sale-details-field">' . $tmpBoat['offices']['office']['val']['fax1']['val'] . '</div>
                </div>';
        }

        if ( ! empty($tmpBoat['offices']['office']['val']['languages']['val'])) {
            $contactHtml .= '
                <div class="shiplisting-sale-details-row">
                    <div class="shiplisting-sale-details-label">' . $this->translation['offices']['languages'] . ':</div>
                    <div class="shiplisting-sale-details-field">' . $tmpBoat['offices']['office']['val']['languages']['val'] . '</div>
                </div>';
        }

        if ( ! empty($tmpBoat['offices']['office']['val']['website_sale']['val'])) {
            $contactHtml .= '
                <div class="shiplisting-sale-details-row">
                    <div class="shiplisting-sale-details-label"></div>
                    <div class="shiplisting-sale-details-field">' . $tmpBoat['offices']['office']['val']['website_sale']['val'] . '</div>
                </div>';
        }
        $boat_data['contact_details']['html'] = $contactHtml;

        // handle countries
        $countriesHtml = '';
        if (is_array($countries['selects'])) {
            $countriesHtml .= '<option value="" selected>----------------------------</option>';
            if (is_array($countries['selects']['ct_spec'])) {
                foreach ($countries['selects']['ct_spec'] as $key => $ct_spec) {
                    $countriesHtml .= '<option value="' . $ct_spec['id'] . '">' . $ct_spec['name'] . '</option>';
                }
                $countriesHtml .= '<option value="">----------------------------</option>';
            }
            if (is_array($countries['selects']['ccountry'])) {
                foreach ($countries['selects']['ccountry'] as $key => $ct_spec) {
                    $countriesHtml .= '<option value="' . $ct_spec['id'] . '">' . $ct_spec['name'] . '</option>';
                }
            }
            $boat_data['contact_details']['countries']['html'] = $countriesHtml;
        }

        $boat_data["imageData"]["closePng"] = get_bloginfo('url') . '/wp-content/plugins/shiplisting/public/images/x-mark-16.png';


        wp_enqueue_script("shiplisting_public", plugin_dir_url(__DIR__) . 'public/js/shiplisting-public.js',
            array('jquery'), SHIPLISTING_VERSION, false);
        wp_localize_script("shiplisting_public", 'shiplisting_public',
            array('ajax_url' => admin_url('admin-ajax.php')));

        $ppp['data']                = $boat_data;
        $ppp['translation']['boat'] = $tmpBoat['translation'];

        return $ppp;
    }

    public function get_boat_images($bid, $size = "huge")
    {
        $tmpBoatImages = json_decode($this->send_request('boat-data', 'pictures',
            '&bid=' . $bid . '&pic_size=' . $size . ''));

        return $tmpBoatImages;
    }

    public function get_smiliar_boats()
    {
        //todo: handle boat id or category
        $tmpSimliarBoats = json_decode($this->send_request('boat-data', 'similar-boats', ''));
    }

    public function get_featured_boats()
    {
        //todo: handle filter
        $tmpSimliarBoats = json_decode($this->send_request('boat-data', 'featured-boats', ''), true);
        if ( ! $tmpSimliarBoats) {
            return;
        }
    }

    public function sourceSwitcher($source)
    {
        if ( ! Shiplisting_Shortcodes::$api) {
            Shiplisting_Shortcodes::$api = $GLOBALS['shiplisting'];
        }

        if ($source == "0") {
            Shiplisting_Shortcodes::$api->update_uri('https://api.yachtall.com/');
        } elseif ($source == "1") {
            Shiplisting_Shortcodes::$api->update_uri('https://api.happycharter.com/');
        }
    }

    public function public_ajax_get_all_filters()
    {
        $source = $_POST['source'];
        $search = $_POST['search'];
        $this->sourceSwitcher($source);

        $data = $this->ajax_get_all_filters(($search == "1") ? true : false);
        echo json_encode($data);
        exit;
    }

    public function ajax_get_all_filters($fieldtype = false)
    {
        $tmpBoat = json_decode($this->send_request('boat-data', 'all-filters', ($fieldtype) ? '&fieldtype=search' : ''),
            true);
        if ( ! $tmpBoat) {
            return;
        }

        if ( ! Shiplisting_Shortcodes::$api) {
            Shiplisting_Shortcodes::$api = $GLOBALS['shiplisting'];
        }

        if ( ! $this->translation) {
            $this->translation = Shiplisting_Shortcodes::$api->ajax_get_translation();
        }


        return $tmpBoat;
    }

    public function ajax_get_defined_filters($fields)
    {
        $tmpBoat = json_decode($this->send_request('boat-data', 'search-form', "&trans=1&fields=" . $fields), true);
        if ( ! $tmpBoat) {
            return;
        }

        $string_store       = '';
        $return_store       = [];
        $tmpTranslation     = $tmpBoat['translation'];
        $price_fields       = ["sprc", "wkprc"];
        $numeric_exceptions = [];
        $length_fields      = ["lng"];

        // prepare html objects
        foreach ($tmpBoat['selects'] as $key => $value) {
            $is_fromTo_field = false;
            // check if value is workable array
            if (is_array($value)) {
                // setup translation
                $i18n_key = ($tmpTranslation['search'][$key]) ? $tmpTranslation['search'][$key] : '';
                // check if value is numeric
                if (is_numeric($value[0]['name']) && ! in_array($key, $numeric_exceptions)) {
                    $is_fromTo_field = true;
                    // field is numeric from to
                    // todo double select from to
                    $tmp_select_store           = '';
                    $unit_finder                = (in_array($key,
                        $length_fields)) ? ' (' . $tmpTranslation['words']['m'] . ')' : '';
                    $price_finder               = (in_array($key, $price_fields)) ? ' (€)' : '';
                    $return_store[$key]['html'] .= '
        <div class="shiplisting-filter-column ' . $key . '">
            <div class="shiplisting-filter-column-label">
                ' . $i18n_key . $unit_finder . $price_finder . '
            </div>
            <div class="shiplisting-filter-column-content double">
                <div class="first">
                    <select id="' . $key . 'f" name="' . $key . 'f">
                        <option value="-1" selected="selected">' . $tmpTranslation['words']['from'] . '</option>';

                    foreach ($value as $option) {
                        $return_store[$key]['html'] .= '<option value="' . $option['id'] . '">' . $option['name'] . '</option>';
                        $tmp_select_store           .= '<option value="' . $option['id'] . '">' . $option['name'] . '</option>';
                    }

                    $return_store[$key]['html'] .= '
                    </select>
                </div>
                <div class="second">
                    <select id="' . $key . 't" name="' . $key . 't">
                        <option value="-1" selected="selected">' . $tmpTranslation['words']['to'] . '</option>
                        ' . $tmp_select_store . '
                    </select>
                </div>
            </div>
        </div>
                    ';
                } else {
                    // field is not numeric
                    // todo one select with all options
                    $return_store[$key]['html'] .= '
        <div class="shiplisting-filter-column ' . $key . '">
            <div class="shiplisting-filter-column-label">
                ' . $i18n_key . '
            </div>
            <div class="shiplisting-filter-column-content">
                <select id="' . $key . '" name="' . $key . '">
                    <option value="-1">------------------</option>';

                    foreach ($value as $option) {
                        $return_store[$key]['html'] .= '<option value="' . $option['id'] . '">' . $option['name'] . '</option>';
                    }

                    $return_store[$key]['html'] .= '
                </select>
            </div>
        </div>';
                }
                $string_store .= $return_store[$key]['html'];
            }
        }

        return $string_store;
    }

    public function get_plugin_settings()
    {
        global $wpdb;

        $result = $wpdb->get_results("SELECT * FROM wp_shiplisting_settings ORDER BY id ASC LIMIT 1");
        if ($result && sizeof($result) > 0) {
            return $result[0];
        }
    }

    public function api_array_to_string($arr)
    {
        $tmpDays = [];

        if ($arr == null) {
            return;
        }

        foreach ($arr as $days) {
            $tmpDays[] = $days['val'];
        }

        return implode(', ', $tmpDays);
    }

    public function ajax_get_detail_views()
    {
        global $wpdb;
        $tmpdata = [];

        if ($wpdb) {
            $result = $wpdb->get_results("SELECT `id`, `path`, `title`, `vars` FROM `wp_shiplisting_routes` WHERE `callback` = 'display_boat_details'");
            if ($result) {

                $array = json_decode(json_encode($result), true);
                foreach ($array as $key => $route) {
                    $vars = json_decode($route['vars'], true);
                    if ($vars[2]['source'] == @$_POST['source']) {
                        $tmpdata[] = $route;
                    }
                }
            }
        }
        $tmpdata = json_encode($tmpdata, JSON_FORCE_OBJECT);
        echo $tmpdata;
        exit;
    }

    public function get_detail_view_link_by_id($detailViewId)
    {
        global $wpdb;

        if ($detailViewId) {
            $result = $wpdb->get_results("SELECT `path` FROM `wp_shiplisting_routes` WHERE `id` = '" . $detailViewId . "'");
            if ($result) {
                if ( ! empty($result[0]->{'path'})) {
                    return substr($result[0]->{'path'}, 1, stripos($result[0]->{'path'}, '/') - 1);
                }
            }
        }

        return false;
    }

    public function get_current_route($current_uri, $routes_id = 0)
    {
        global $wpdb;

        $result = null;
        if ($routes_id != 0) {
            $result = $wpdb->get_results('SELECT * FROM `wp_shiplisting_routes` WHERE `id`=' . $routes_id);
        } elseif ( ! empty($current_uri)) {
            $result = $wpdb->get_results("SELECT `id`, `vars`, `title`, `adv_filter`, `linked_detail_view`, `language` FROM `wp_shiplisting_routes` WHERE `path` = '$current_uri'");
        }

        if ($result) {
            if ($result[0]->{'id'}) {
                $tmpVars                                   = json_decode($result[0]->{'vars'}, true);
                Shiplisting_Shortcodes::$api->currentRoute = [
                    'id'                 => $result[0]->{'id'},
                    'path'               => $current_uri,
                    'adv_filter'         => ($result[0]->{'adv_filter'}) ? implode(',',
                        json_decode($result[0]->{'adv_filter'}, true)) : '',
                    'source'             => $tmpVars[sizeof($tmpVars) - 1]['source'],
                    'linked_detail_view' => Shiplisting_Shortcodes::$api->get_detail_view_link_by_id($result[0]->{'linked_detail_view'}),
                    'language'           => ($result[0]->{'language'}) ? $result[0]->{'language'} : '',
                    'title'              => $result[0]->{'title'}
                ];

                unset($result);

                return true;
            }
        }

        return false;
    }

    public function public_ajax_get_api_langues()
    {
        $source = $_POST['source'];
        $type   = $_POST['type'];
        $this->sourceSwitcher($source);

        $fields = '';
        $data   = '';
        if ( ! empty($type)) {
            $fields = '&viewtype=' . $type;
            $data   = $this->send_request('info-special', 'plugin-info', $fields);
        }
        echo $data;
        exit;
    }

    public function resolve_title_placeholders($routes_id, $bid)
    {
        global $wpdb;

        // check if route id is empty
        if (empty($routes_id)) {
            return false;
        }

        // trying to init current class route
        if ($this->get_current_route('', $routes_id)) {
            $fields    = [];
            $tmp_title = Shiplisting_Shortcodes::$api->currentRoute['title'];

            // check if title is empty
            if ( ! empty($tmp_title)) {
                $tmp_title = preg_replace_callback("/\{(.*?)\}/i", function ($result) use (&$fields) {
                    $fields[] = $result[1];

                    return '{' . $result[1] . '}';
                }, $tmp_title);

                if (sizeof($fields) > 0) {
                    // prepare api endpoint
                    if ( ! empty(Shiplisting_Shortcodes::$api->currentRoute['source'])) {
                        $this->sourceSwitcher(Shiplisting_Shortcodes::$api->currentRoute['source']);
                    }

                    // send request to api
                    $data = json_decode($this->send_request('info-special', 'get-placeholders',
                        '&bid=' . $bid . '&fields=' . implode(',', $fields)), true);
                    $data = $data['fields'];

                    // check for data
                    if (sizeof($data) > 0) {
                        $tmp_title = preg_replace_callback("/\{(.*?)\}/i", function ($result) use ($data) {
                            $placeholder = $result[1];

                            return $data[$placeholder];
                        }, $tmp_title);

                        return $tmp_title;
                    }
                }
            }

            return $tmp_title;
        }

        return false;
    }

    public function public_route_exists()
    {
        global $wpdb;

        $route_name = $_POST['route_name'];
        $result     = $wpdb->get_results("SELECT `id` FROM `wp_shiplisting_routes` WHERE `name`='$route_name'");

        if ($result && $result[0]->{'id'}) {
            echo $result[0]->{'id'};
            exit;
        } else {
            echo '0';
            exit;
        }
    }

    public function public_ajax_get_api_title_placeholders()
    {
        $source = $_POST['source'];
        $this->sourceSwitcher($source);

        $data = $this->send_request('info-special', 'get-placeholders', '');
        echo $data;
        exit;
    }

    public function public_path_exists()
    {
        global $wpdb;

        $path = $_POST['path'];
        if (empty($path)) {
            return;
        }

        $type = $_POST['type'];
        if ($type == "detail") {
            $type = 'display_boat_details';
        } elseif ($type == "list") {
            $type = 'display_boats';
        }

        $result = $wpdb->get_results("SELECT `id` FROM `wp_shiplisting_routes` WHERE `path`='$path' AND `callback`='$type'");
        if ($result && $result[0]->{'id'}) {
            echo $result[0]->{'id'};
            exit;
        } else {
            echo '0';
            exit;
        }
    }

    public function public_get_translation()
    {
        $current_uri = $_POST['uri'];
        $data        = '';
        if ( ! empty($current_uri)) {
            // encode because function does decode
            $data = json_encode($this->ajax_get_translation());
        }
        echo $data;
        exit;
    }

    //new
    public function ajax_api_init_route()
    {
        global $wpdb;

        $uri = $_POST['post_data']['uri'];
        if ( ! empty($uri)) {
            $splitter   = preg_split("#/#", $uri);
            $route_path = $splitter[3];

            $result = $wpdb->get_results("SELECT * FROM `wp_shiplisting_routes` WHERE `path` LIKE '%$route_path%' LIMIT 1");
            if ($result) {
                echo json_encode($result[0]);
            }
        }
        exit;
    }
}

class Shiplisting_Shortcodes
{
    /** @var Shiplisting_Api $api */
    public static $api;

    public static function get_boats($atts)
    {
        if ( ! Shiplisting_Shortcodes::$api) {
            Shiplisting_Shortcodes::$api = $GLOBALS['shiplisting'];
        }

        $atts = shortcode_atts(array(
            'filter' => '',
        ), $atts, 'shiplisting::get_boats');

        return Shiplisting_Shortcodes::$api->get_boats($atts['filter']);
    }

    public static function get_boat($atts)
    {
        if ( ! Shiplisting_Shortcodes::$api) {
            Shiplisting_Shortcodes::$api = $GLOBALS['shiplisting'];
        }

        $atts = shortcode_atts(array(
            'boat_id' => '',
        ), $atts, 'shiplisting::get_boat');

        return Shiplisting_Shortcodes::$api->get_boat($atts['boat_id']);
    }

    public static function get_boat_images($atts)
    {
        if ( ! Shiplisting_Shortcodes::$api) {
            Shiplisting_Shortcodes::$api = $GLOBALS['shiplisting'];
        }

        $atts = shortcode_atts(array(
            'boat_id' => '',
        ), $atts, 'shiplisting::get_boat_images');

        return Shiplisting_Shortcodes::$api->get_boat_images($atts['boat_id']);
    }

    public static function get_featured_boats()
    {
        if ( ! Shiplisting_Shortcodes::$api) {
            Shiplisting_Shortcodes::$api = $GLOBALS['shiplisting'];
        }

//        $atts = shortcode_atts( array(
//                                    'boat_id' => '',
//                                ), $atts, 'shiplisting::get_boat_images' );

        return Shiplisting_Shortcodes::$api->get_featured_boats();
    }
}

abstract class Shiplisting_SourceType
{
    const YACHTALL = 0;
    const HAPPYCHARTER = 1;
}

include_once(ABSPATH . 'wp-admin/includes/plugin.php');

class Shiplisting_Router
{
    public static $translation = null;

    public static function init()
    {
        if ( ! Shiplisting_Shortcodes::$api) {
            Shiplisting_Shortcodes::$api = $GLOBALS['shiplisting'];
        }

        add_action('wp_router_generate_routes', array(get_class(), 'generate_routes'), 10, 1);
    }

    public static function generate_routes(WP_Router $router)
    {
        global $wpdb;

        if ( ! is_plugin_active('shiplisting/shiplisting.php')) {
            return;
        }

        $result = $wpdb->get_results("SELECT * FROM wp_shiplisting_routes");
        foreach ($result as $route) {
            $vars = json_decode($route->{'vars'}, true);
            if (is_array($vars)) {
                $varsArr = array();
                if (is_array($vars[0])) {
                    foreach ($vars as $var) {
                        foreach ($var as $key => $val) {
                            $varsArr[$key] = $val;
                        }
                    }
                } else {
                    $varsArr = $vars;
                }
            } else {
                $varsArr = $vars;
            }

            $tmp_title = $route->{'title'};

            $router->add_route($route->{'name'}, array(
                'path'            => $route->{'path'},
                'query_vars'      => (strlen($route->{'vars'}) == 0) ? array() : $varsArr,
                'page_callback'   => array(get_class(), $route->{'callback'}),
                'page_arguments'  => (strlen($route->{'arguments'}) == 0) ? null : json_decode($route->{'arguments'},
                    true),
                'access_callback' => true,
                'title'           => $route->{'title'},
                'title_callback'  => ($route->{'callback'} == "display_boat_details") ? [
                    "GET" => array(get_class(), 'title_callback')
                ] : true,
                'title_arguments' => ['boat_id']
            ));
        }
    }

    public static function title_callback($boat_id)
    {
        // identify route
        $current_uri = $_SERVER['REQUEST_URI'];
        $current_uri = preg_split("#/#", $current_uri);

        if (count($current_uri) > 0) {
            if (count($current_uri) == 4) {
                $current_uri = ($current_uri[count($current_uri) - 3]) ? $current_uri[count($current_uri) - 3] : "";
            } else {
                $current_uri = ($current_uri[count($current_uri) - 2]) ? $current_uri[count($current_uri) - 2] : "";
            }
        }
        if ( ! $current_uri && empty($current_uri)) {
            return "couldnt get current uri correctly.";
        }

        Shiplisting_Shortcodes::$api->get_current_route($current_uri);

        return Shiplisting_Shortcodes::$api->resolve_title_placeholders(Shiplisting_Shortcodes::$api->currentRoute['id'],
            $boat_id);
    }

    private static function replace_placeholder_boat_details($boat_id, $content, $source)
    {
        if ( ! $boat_id) {
            return;
        }

        if ( ! Shiplisting_Shortcodes::$api) {
            Shiplisting_Shortcodes::$api = $GLOBALS['shiplisting'];
        }

        $plugin_data = Shiplisting_Shortcodes::$api->get_plugin_settings();
        if ( ! $plugin_data) {
            return 'error getting current plugin settings.';
        }

        if ($plugin_data->{'active'} == "0") {
            return 'plugin is set to disabled. please try again later.';
        }

        if ($plugin_data->{'maintenance'} == "1") {
            return 'plugin is set to maintenance mode. please try again later.';
        }

        if ( ! empty($source)) {
            if ((int)$source == Shiplisting_SourceType::YACHTALL) {
                Shiplisting_Shortcodes::$api->update_uri('https://api.yachtall.com/');
            } elseif ((int)$source == Shiplisting_SourceType::HAPPYCHARTER) {
                Shiplisting_Shortcodes::$api->update_uri('https://api.happycharter.com/');
            }
        }

        // identify route
        $current_uri = $_SERVER['REQUEST_URI'];
        $current_uri = preg_split("#/#", $current_uri);

        if (count($current_uri) > 0) {
            if (count($current_uri) == 4) {
                $current_uri = ($current_uri[count($current_uri) - 3]) ? $current_uri[count($current_uri) - 3] : "";
            } else {
                $current_uri = ($current_uri[count($current_uri) - 2]) ? $current_uri[count($current_uri) - 2] : "";
            }
        }
        if ( ! $current_uri && empty($current_uri)) {
            return "couldnt get current uri correctly." . print_r($current_uri, true);
        }

        Shiplisting_Shortcodes::$api->get_current_route('^' . $current_uri . '/(.*?)$', 0);
        if ( ! Shiplisting_Shortcodes::$api->currentRoute) {
            return "couldnt get current route correctly." . print_r($current_uri, true);
        }

        $boat_data = Shiplisting_Shortcodes::$api->ajax_get_boat($boat_id);
        if ( ! $boat_data) {
            return 'Boat not found.';
        }

        self::$translation = $boat_data['translation'];
        $boat_data         = $boat_data['data'];

        if ( ! $content) {
            return;
        }

        echo preg_replace_callback("/\{(.*?)\}/i", function ($result) use ($boat_data, $boat_id, $plugin_data) {
            $placeholder   = $result[1];
            $isTranslation = false;

            if (strpos($placeholder, 'i18n_') > -1) {
                $placeholder   = substr($placeholder, 5);
                $isTranslation = true;
            }

            if (strpos($placeholder, 'doubleValue(') > -1) {
                $placeholder = substr($placeholder, 12);
                $placeholder = substr($placeholder, 0, strpos($placeholder, ')'));
                $delimiter   = '';

                if (strpos($placeholder, '"') > -1) {
                    $delimiterTmp = substr($placeholder, strpos($placeholder, '"') + 1);
                    $delimiterTmp = substr($delimiterTmp, 0, strpos($delimiterTmp, '"'));
                    $placeholder  = substr($placeholder, 0, strpos($placeholder, '"') - 1);

                    $arguments = explode(',', $placeholder);
                    array_push($arguments, $delimiterTmp);
                    $delimiter = $delimiterTmp;
                }

                $valuePair = [
                    array_get_value($boat_data, explode('-', $arguments[0])),
                    array_get_value($boat_data, explode('-', $arguments[1]))
                ];

                if (strpos($valuePair[0], 'doubleValue(') !== false
                || (!empty($valuePair[1]) && strpos($valuePair[1], 'doubleValue(') !== false)) {
                    return '';
                }

                if (empty($valuePair[1])) {
                    return $valuePair[0];
                }

                return $valuePair[0] . $delimiter . $valuePair[1];
            }

            if (strpos($placeholder, 'getValue(') > -1) {
                $placeholder = substr($placeholder, 9);
                $placeholder = substr($placeholder, 0, strpos($placeholder, ')'));
                $delimiter   = '';

                if (strpos($placeholder, '"') > -1) {
                    $delimiterTmp = substr($placeholder, strpos($placeholder, '"') + 1);
                    $delimiterTmp = substr($delimiterTmp, 0, strpos($delimiterTmp, '"'));
                    $placeholder  = substr($placeholder, 0, strpos($placeholder, '"') - 1);
                    $delimiter    = $delimiterTmp;
                }

                $value = array_get_value($boat_data, explode('-', $placeholder));
                if ( ! empty($value)) {
                    return $value . $delimiter;
                }
            }

            if ($placeholder == "contactform_enabled" && $plugin_data->{'contact_form'} == "1") {
                return " enabled";
            }

            if ($placeholder == "possibilities") {
                $fields  = [];
                $tmpData = [];
                foreach ($boat_data['charter_data']['possibilities'] as $key => $value) {
                    $fields[] = $key;
                }

                foreach ($fields as $field) {
                    $val = ($boat_data['charter_data']['possibilities'][$field]['val']) ? $boat_data['charter_data']['possibilities'][$field]['val'] : '';
                    if ( ! empty($val) && $val == 'true') {
                        $tmpTrans = self::$translation['boat']['charter_data']['possibilities'][$field];
                        if ($field == "pets") {
                            if ($val) {
                                $tmpTrans = self::$translation['boat']['charter_data']['possibilities']['pets_yes'];
                            }
                        }
                        $tmpData[] = $tmpTrans;
                    }
                }

                return implode(', ', $tmpData);
            }

            if ($placeholder == "possibilities_table") {
                $fields  = [];
                $tmpData = [];
                foreach ($boat_data['charter_data']['possibilities'] as $key => $value) {
                    if ($value['val'] == 'true' && $key != 'sale_possible') {
                        $key = str_replace('_charter', '', $key);
                        if ($key == 'berth') {
                            $fields[] = 'berth_week';
                        } elseif ($key == 'cabin') {
                            $fields[] = 'cabin_week';
                        } else {
                            $fields[] = 'boat_' . $key;
                        }
                    }
                }

                foreach ($fields as $field) {
                    $possib = (@$boat_data['charter_data']['prices']['val'][$field]) ? $boat_data['charter_data']['prices']['val'][$field] : null;
                    if (is_array($possib)) {
                        $tmpData[] = '
                            <div class="row bold">
                                <div class="left">' . self::$translation['boat']['charter_data']['prices'][$field] . ':</div>
                                <div class="right">' . $possib['min'] . ' - ' . $possib['max'] . ' ' . $boat_data['charter_data']['prices']['attr']['currency_sign'] . '</div>
                            </div>';
                    }
                }

                return implode('', $tmpData);
            }

            if ($placeholder == "check_in_days") {
                return Shiplisting_Shortcodes::$api->api_array_to_string($boat_data['charter_data']['check_in_days']);
            }

            if ($placeholder == "regions") {
                return Shiplisting_Shortcodes::$api->api_array_to_string($boat_data['charter_data']['areas']['regions']);
            }

            if ($placeholder == "countries") {
                return Shiplisting_Shortcodes::$api->api_array_to_string($boat_data['charter_data']['areas']['countries']);
            }

            if ($placeholder == "water_areas") {
                return Shiplisting_Shortcodes::$api->api_array_to_string($boat_data['charter_data']['areas']['water_areas']);
            }

            if ($placeholder == "ports") {
                return Shiplisting_Shortcodes::$api->api_array_to_string($boat_data['charter_data']['areas']['ports']);
            }

            if ($placeholder == "pets") {
                if ($boat_data['charter_data']['possibilities']['pets']['val'] == "true") {
                    return '    <div class="row bold">
                                <div class="left">' . self::$translation['boat']['charter_data']['possibilities']['pets_yes'] . ':</div>
                                <div class="right">' . $boat_data['charter_data']['possibilities']['pets']['attr']['price'] . ' ' . $boat_data['charter_data']['prices']['attr']['currency_sign'] . '</div>
                            </div>';
                }
            }

            if ($placeholder == "caution") {
                if (is_numeric($boat_data['charter_data']['extra_charged']['damage_deposit']['val'])) {
                    return $boat_data['charter_data']['extra_charged']['damage_deposit']['val'] . ' ' . $boat_data['charter_data']['prices']['attr']['currency_sign'];
                } else {
                    return $boat_data['charter_data']['extra_charged']['damage_deposit']['val'];
                }
            }

            if ($placeholder == "season_prices") {
                $boxTmpl = '<div class="shiplisting-charter-season-prices-row">';
                foreach ($boat_data['charter_data']['prices']['val'] as $key => $type) {
                    if (is_array($type['season_prices'])) {
                        // title
                        $boxTmpl   .= '<div class="shiplisting-charter-season-prices-title">';
                        $build_key = substr($key, stripos($key, 'boat_') + 5, strlen($key));
                        $build_key = 'prices_in_curr_' . $build_key;
                        $boxTmpl   .= str_replace('XXX_curr',
                            $boat_data['charter_data']['prices']['attr']['currency_sign'],
                            self::$translation['boat']['charter_data'][$build_key]);
                        $boxTmpl   .= '</div>';

                        $boxTmpl .= '<div class="shiplisting-charter-season-prices-boxes-wrapper">';
                        if (is_array($type)) {
                            foreach ($type['season_prices'] as $prices) {
                                // content boxes
                                $boxTmpl .= '<div class="shiplisting-charter-season-prices-box">';
                                // content box title
                                $boxTmpl .= '<div class="shiplisting-charter-season-prices-box-title">';
                                $boxTmpl .= $prices['attr']['date_from'] . '<br>' . self::$translation['boat']['words']['to'] . '<br>' . $prices['attr']['date_to'];
                                $boxTmpl .= '</div>';
                                // content box content
                                $boxTmpl .= '<div class="shiplisting-charter-season-prices-box-content">';
                                $boxTmpl .= $prices['val'];
                                $boxTmpl .= '</div>';
                                // content boxes end
                                $boxTmpl .= '</div>';
                            }
                        }
                        $boxTmpl .= '</div>';
                    }
                }

                return $boxTmpl;
            }

            if ($placeholder == "extras") {
                $rowTmpl = '';
                foreach ($boat_data['charter_data']['company_extras'] as $extra) {
                    if ($extra['val']) {
                        $rowTmpl .= '<div class="shiplisting-charter-extra-row">';
                        $rowTmpl .= '<div class="left">' . $extra['attr']['name'] . ':</div>';
                        $rowTmpl .= '<div class="right">' . $extra['val'] . '</div>';
                        $rowTmpl .= '</div>';
                    }
                }

                return $rowTmpl;
            }

            if ($placeholder == "extras_additional") {
                $tmpData = [];
                if (is_array($boat_data['charter_data']['additional'])) {
                    foreach ($boat_data['charter_data']['additional'] as $additional) {
                        $tmpData[] = $additional['val'];
                    }
                }

                return implode(', ', $tmpData);
            }

            if ($placeholder == "discounts") {
                $tmpData = '';
                $cats    = [];

                if ( ! $boat_data['charter_data']['discounts']) {
                    return;
                }

                foreach ($boat_data['charter_data']['discounts'] as $discount_key => $discount_cat) {
                    if (is_array($discount_cat)) {
                        foreach ($boat_data['charter_data']['discounts'][$discount_key] as $discount) {
                            $str = $discount['attr']['charter_from'] . ' - ' . $discount['attr']['charter_to'];
                            if ( ! empty($discount['attr']['booking_to'])) {
                                $str .= ' (' . self::$translation['boat']['charter_data']['booking_until'] . ' ' . $discount['attr']['booking_to'] . ')';
                            }
                            $str                               .= ' <s>' . $discount['attr']['origin'] . '</s> <b>' . $discount['val'] . ' ' . $boat_data['charter_data']['prices']['attr']['currency_sign'] . '</b><br>';
                            $cats[$discount['attr']['kind']][] = $str;
                        }
                    }
                }
                foreach ($cats as $key => $cat) {
                    $tmpData .= '<h3>' . $key . ':</h3>';
                    foreach ($cats[$key] as $str) {
                        $tmpData .= $str;
                    }
                }

                return $tmpData;
            }

            if ($placeholder == "other_extras_charter") {
                if ($boat_data['boat_data']['general_data']['flybridge']['val'] == self::$translation['boat']['words']['yes']) {
                    return self::$translation['boat']['boat_data']['general_data']['flybridge'];
                } else {
                    return "";
                }
            }

            $api_path = explode('-', $placeholder);
            if ($isTranslation) {
                return array_get_value(self::$translation, $api_path);
            }

            if ($placeholder == "boatid") {
                return $boat_id;
            }

            if ($placeholder == "ip_address") {
                if ( ! empty($_SERVER['HTTP_CLIENT_IP'])) {
                    return $_SERVER['HTTP_CLIENT_IP'];
                } elseif ( ! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    return $_SERVER['HTTP_X_FORWARDED_FOR'];
                } else {
                    return $_SERVER['REMOTE_ADDR'];
                }
            }

            return array_get_value($boat_data, $api_path);
        }, $content);
    }

    public static function display_boat_details($boat_id, $template, $source)
    {
        if ( ! $boat_id) {
            return;
        }

        $template = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/shiplisting/public/js/templates/' . $template);
        if ( ! $template) {
            return;
        }

        $template = self::replace_placeholder_boat_details($boat_id, $template, $source);

        if (strpos($template, 'doubleValue(') !== false) {
            return '';
        }

        return $template;
    }

    private static function replace_placeholder_boats($boatTemplate, $content, $filterId, $hitsByPage, $source)
    {
        global $wpdb;

        if ( ! $content) {
            return;
        }

        if ( ! Shiplisting_Shortcodes::$api) {
            Shiplisting_Shortcodes::$api = $GLOBALS['shiplisting'];
        }

        $plugin_data = Shiplisting_Shortcodes::$api->get_plugin_settings();
        if ( ! $plugin_data) {
            return 'error getting current plugin settings.';
        }

        if ($plugin_data->{'active'} == "0") {
            return 'plugin is set to disabled. please try again later.';
        }

        if ($plugin_data->{'maintenance'} == "1") {
            return 'plugin is set to maintenance mode. please try again later.';
        }

        if ( ! empty($source)) {
            if ((int)$source == Shiplisting_SourceType::YACHTALL) {
                Shiplisting_Shortcodes::$api->update_uri('https://api.yachtall.com/');
            } elseif ((int)$source == Shiplisting_SourceType::HAPPYCHARTER) {
                Shiplisting_Shortcodes::$api->update_uri('https://api.happycharter.com/');
            }
        }

        // identify route
        $current_uri = $_SERVER['REQUEST_URI'];
        $current_uri = preg_split("#/#", $current_uri);
        if (count($current_uri) > 0) {
            if (count($current_uri) == 3) {
                $current_uri = ($current_uri[count($current_uri) - 2]) ? $current_uri[count($current_uri) - 2] : "";
            } else {
                $current_uri = ($current_uri[count($current_uri) - 1]) ? $current_uri[count($current_uri) - 1] : "";
            }
        }
        if ( ! $current_uri && empty($current_uri)) {
            return "couldnt get current uri correctly.";
        }

        Shiplisting_Shortcodes::$api->get_current_route($current_uri);
        if ( ! Shiplisting_Shortcodes::$api->currentRoute) {
            return "couldnt get current route correctly.";
        }

        if ( ! self::$translation) {
            self::$translation = Shiplisting_Shortcodes::$api->ajax_get_translation();
        }

        $filter = '';
        if (( ! empty($filterId) || $filterId != "0") && $plugin_data->{'filtering_standard'} == "1") {
            $result = $wpdb->get_results("SELECT * FROM wp_shiplisting_filter WHERE id = $filterId");
            if ($result) {
                if ( ! empty($result[0]->{'fields_to_filter'})) {
                    $fieldsToFilter = json_decode($result[0]->{'fields_to_filter'}, true);
                    $filterStr      = '';
                    foreach ($fieldsToFilter as $value) {
                        foreach ($value as $k => $v) {
                            $filterStr .= '&' . $k . '=' . $v;
                        }
                    }
                    if ( ! empty($filterStr)) {
                        $filter = $filterStr;
                    }
                }
            }
        }

        $filter .= '&bnr=' . $hitsByPage;
        $page   = 1;
        if ($_GET['page']) {
            $page   = $_GET['page'];
            $filter .= '&pg=' . $page;
        } else {
            $filter       .= '&pg=1';
            $_GET['page'] = "1";
        }

        // store in var
        $standard_filter = $filter;

        $actual_link    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $url_components = parse_url($actual_link);
        parse_str($url_components['query'], $params);
        foreach ($params as $key => $value) {
            if ($key != "page") {
                $filter .= '&' . $key . '=' . $value;
            }
        }

        if ($plugin_data->{'filtering_advanced'} == "0") {
            $filter = $standard_filter;
        }

        if ( ! empty(Shiplisting_Shortcodes::$api->currentRoute['source'])) {
            Shiplisting_Shortcodes::$api->sourceSwitcher(Shiplisting_Shortcodes::$api->currentRoute['source']);
        }

        $response  = Shiplisting_Shortcodes::$api->ajax_get_boats($filter);
        $boat_data = $response['adverts'];

        $hits = $response['info']['hits'];
        if ($hits >= $hitsByPage) {
            $pagination['html'] = '<div class="shiplisting-boats-pagination-wrapper"><ul>';

            $pages = ceil($hits / $hitsByPage);

            if (($page - 1) > 0) {
                $pagination['html'] .= '<li class="shiplisting-pagination-page previous-page"><a href="' . (self::uriReplacePageNumber($page - 1)) . '">«</a></li>';
            }

            $max_range = ($page + 10);
            if ($max_range >= $pages) {
                $max_range = $pages;
            }

            $min_range = ($page - 10);
            if ($min_range <= 0) {
                $min_range = 0;
            }

            for ($i = $min_range; $i < $max_range; $i++) {
                if (($i + 1) == $page) {
                    $active = ' active';
                } else {
                    $active = '';
                }
                $pagination['html'] .= '<li class="shiplisting-pagination-page' . $active . '"><a href="' . (self::uriReplacePageNumber($i + 1)) . '">' . ($i + 1) . '</a></li>';
            }

            if (($page + 1) <= $pages) {
                $pagination['html'] .= '<li class="shiplisting-pagination-page next-page"><a href="' . (self::uriReplacePageNumber($page + 1)) . '">»</a></li>';
            }

            $pagination['html'] .= '</ul></div>';
        }

        $i = 0;
        if ($boat_data) {
            foreach ($boat_data as $boatObj) {
                $boatOrg = $boatObj;
                $boatObj = $boatObj['val'];

                if ($source == "0") {
                    $boatTemplate = 'template-shiplisting-boat-obj.html';
                } elseif ($source == "1") {
                    $boatTemplate = 'template-shiplisting-happycharter-boat-obj.html';
                }
                $template = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/shiplisting/public/js/templates/' . $boatTemplate);

                if ( ! empty($template)) {
                    $template = preg_replace_callback("/\{(.*?)\}/i",
                        function ($result) use ($boatObj, $boatTemplate, $boatOrg, $source) {
                            $placeholder   = $result[1];
                            $isTranslation = false;

                            if (strpos($placeholder, 'i18n_') > -1) {
                                $placeholder   = substr($placeholder, 5);
                                $isTranslation = true;
                            }

                            if (strpos($placeholder, 'doubleValue(') > -1) {
                                $placeholder = substr($placeholder, 12);
                                $placeholder = substr($placeholder, 0, strpos($placeholder, ')'));
                                $delimiter   = '';

                                if (strpos($placeholder, '"') > -1) {
                                    $delimiterTmp = substr($placeholder, strpos($placeholder, '"') + 1);
                                    $delimiterTmp = substr($delimiterTmp, 0, strpos($delimiterTmp, '"'));
                                    $placeholder  = substr($placeholder, 0, strpos($placeholder, '"') - 1);

                                    $arguments = explode(',', $placeholder);
                                    array_push($arguments, $delimiterTmp);
                                    $delimiter = $delimiterTmp;
                                }

                                $valuePair = [
                                    array_get_value($boatObj, explode('-', $arguments[0])),
                                    array_get_value($boatObj, explode('-', $arguments[1]))
                                ];

                                if (empty($valuePair[1])) {
                                    return $valuePair[0];
                                }

                                return $valuePair[0] . $delimiter . $valuePair[1];
                            }

                            if ($placeholder == "linked_detail_view") {
                                return Shiplisting_Shortcodes::$api->currentRoute['linked_detail_view'];
                            }

                            if ($placeholder == "boat_id") {
                                return $boatOrg['attr']['code'];
                            }

                            if ($placeholder == "price_details") {
                                $priceDetails = '';

                                if ($source == "0") {
                                    if (is_array($boatObj['sale_data']['price']['val']['old_price'])) {
                                        $priceDetails = '<span class="shiplisting-old-price">' . $boatObj['sale_data']['price']['attr']['currency_sign'] . ' ' . $boatObj['sale_data']['price']['val']['old_price']['val'] . '</span> ';
                                        $priceDetails .= $boatObj['sale_data']['price']['attr']['currency_sign'] . ' ' . $boatObj['sale_data']['price']['val']['price_amount']['val'];

                                        if ( ! empty($boatObj['sale_data']['price']['val']['euro_amount']['val'])) {
                                            $priceDetails .= '<br>(≈ € ' . $boatObj['sale_data']['price']['val']['euro_amount']['val'] . ')';
                                        }
                                    } else {
                                        $priceDetails = $boatObj['sale_data']['price']['attr']['currency_sign'] . ' ' . $boatObj['sale_data']['price']['val']['price_amount']['val'];

                                        if ( ! empty($boatObj['sale_data']['price']['val']['euro_amount']['val'])) {
                                            $priceDetails .= ' (≈ € ' . $boatObj['sale_data']['price']['val']['euro_amount']['val'] . ')';
                                        }
                                    }
                                } elseif ($source == "1") {
                                    $tmp_price = '';
                                    $maxCount  = 2;
                                    $count     = 0;
                                    foreach ($boatObj['charter_data']['prices']['val'] as $cat => $prices) {
                                        if ($count >= $maxCount) {
                                            break;
                                        }
                                        if ( ! empty($tmp_price)) {
                                            $tmp_price .= ', ';
                                        }
                                        $tmp_price .= '<b>' . self::$translation['boat']['charter_data']['prices'][$cat] . ':</b> ' . $boatObj['charter_data']['prices']['val'][$cat]['min'] . ' - ' . $boatObj['charter_data']['prices']['val'][$cat]['max'] . '' . $boatObj['charter_data']['prices']['attr']['currency_sign'];
                                        $count++;
                                    }

                                    if (is_array($boatObj['charter_data']['discounts'])) {
                                        $priceDetails .= '<div class="shiplisting-object-price flex">';
                                        $priceDetails .= '<div class="left">Rabatt möglich</div>';
                                        $priceDetails .= '<div class="right">' . $tmp_price . '</div>';
                                        $priceDetails .= '</div>';
                                    } else {
                                        $priceDetails .= '<div class="shiplisting-object-price">';
                                        $priceDetails .= '' . $tmp_price . '';
                                        $priceDetails .= '</div>';
                                    }
                                }

                                return $priceDetails;
                            }

                            if ($placeholder == "engine_details") {
                                $engineDetails = '';
                                if ( ! empty($boatObj['engine_data']['engine_manufacturer']['val'])) {
                                    $engineDetails .= $boatObj['engine_data']['engine_manufacturer']['val'] . ' ' . $boatObj['engine_data']['engine_model']['val'] . ', ';
                                }

                                if ( ! empty($boatObj['engine_data']['horse_power']['val'])) {
                                    if ($boatObj['engine_data']['engine_quantity']['val'] > 1) {
                                        $engineDetails .= $boatObj['engine_data']['engine_quantity']['val'] . ' x ' . $boatObj['engine_data']['horse_power']['val'];
                                    } else {
                                        $engineDetails .= $boatObj['engine_data']['horse_power']['val'];
                                    }
                                    $engineDetails .= ' ' . $boatObj['engine_data']['horse_power']['attr']['unit'];
                                    $engineDetails .= ' (' . $boatObj['engine_data']['kw_power']['val'] . ' ' . $boatObj['engine_data']['kw_power']['attr']['unit'] . ')';
                                }
                                if ( ! empty($boatObj['engine_data']['fuel']['val'])) {
                                    $engineDetails .= ', ' . $boatObj['engine_data']['fuel']['val'];
                                }

                                if (empty($engineDetails)) {
                                    return "-";
                                }

                                return $engineDetails;
                            }

                            if ($placeholder == "boat_data-passengers-all_cabins-val") {
                                if (empty($boatObj['boat_data']['passengers']['all_cabins']['val'])) {
                                    return "0";
                                }
                            }

                            if ($placeholder == "countries") {
                                return Shiplisting_Shortcodes::$api->api_array_to_string($boatObj['charter_data']['areas']['countries']);
                            }

                            if ($placeholder == "water_areas") {
                                return Shiplisting_Shortcodes::$api->api_array_to_string($boatObj['charter_data']['areas']['water_areas']);
                            }

                            if ($placeholder == "ports") {
                                return Shiplisting_Shortcodes::$api->api_array_to_string($boatObj['charter_data']['areas']['ports']);
                            }

                            //todo handle
                            if ($placeholder == "object_detail_first") {
                                $boat_type     = $boatObj['boat_data']['general_data']['boat_type']['val'];
                                $boat_category = $boatObj['boat_data']['general_data']['boat_category']['val'];
                                $manufacturer  = $boatObj['boat_data']['general_data']['manufacturer']['val'];
                                $boat_class    = $boatObj['sale_data']['boat_class']['val'];
                                $hull_material = $boatObj['boat_data']['general_data']['hull_material']['val'];

                                $tmp = [
                                    'label' => '',
                                    'value' => '',
                                ];
                                if ( ! empty($boat_type)) {
                                    $tmp['label'] .= $boat_type;
                                }
                                if ( ! empty($boat_category) && $source == "1") { //happycharter
                                    $tmp['label'] .= ' / ' . $boat_category . ': ';
                                }
                                if ( ! empty($manufacturer) && $source == "1") {
                                    $tmp['value'] .= $manufacturer;
                                }
                                if ( ! empty($boat_class)) {
                                    $tmp['value'] .= ', ' . $boat_class;
                                }
                                if ( ! empty($hull_material)) {
                                    $tmp['value'] .= ', ' . $hull_material;
                                }

                                if (empty($tmp['value'])) {
                                    return "";
                                }

                                return $tmp['label'] . $tmp['value'];
                            }

                            if ($placeholder == "object_detail_second") {
                                $i18n_loa              = self::$translation['boat']['boat_data']['measure']['loa'];
                                $i18n_beam             = self::$translation['boat']['boat_data']['measure']['beam'];
                                $i18n_year_built_short = self::$translation['boat']['boat_data']['general_data']['year_built_short'];
                                $i18n_all_cabins       = self::$translation['boat']['boat_data']['passengers']['all_cabins'];

                                $loa        = $boatObj['boat_data']['measure']['loa']['val'];
                                $beam       = $boatObj['boat_data']['measure']['beam']['unit']['val'];
                                $loa_attr   = $boatObj['boat_data']['measure']['loa']['attr']['unit'];
                                $year_built = $boatObj['boat_data']['general_data']['year_built']['val'];
                                $all_cabins = $boatObj['boat_data']['passengers']['all_cabins']['val'];

                                $tmp = [];
                                if ( ! empty($i18n_loa)) {
                                    $tmp['label'] .= $i18n_loa;
                                }
                                if ( ! empty($i18n_beam)) {
                                    $tmp['label'] .= ' x ' . $i18n_beam . ': ';
                                }
                                if ( ! empty($loa)) {
                                    $tmp['value'] .= $loa;
                                }
                                if ( ! empty($beam)) {
                                    $tmp['value'] .= ' x ' . $beam . $loa_attr;
                                }
                                if ( ! empty($year_built)) {
                                    $tmp['value'] .= ', ' . $i18n_year_built_short . ': ' . $year_built;
                                }
                                if ( ! empty($all_cabins)) {
                                    $tmp['value'] .= ', ' . $i18n_all_cabins . ': ' . $all_cabins;
                                }

                                if (empty($tmp['value'])) {
                                    return "";
                                }

                                return $tmp['label'] . $tmp['value'];
                            }

                            $api_path = explode('-', $placeholder);
                            if ($isTranslation) {
                                return array_get_value(self::$translation, $api_path);
                            }

                            return array_get_value($boatObj, $api_path);
                        }, $template);

                    $boat_data["boatTemplates"][$i] = $template;
                    $i++;
                }
            }
        }

        echo preg_replace_callback("/\{(.*?)\}/i",
            function ($result) use ($boat_data, $boatTemplate, $pagination, $source, $plugin_data) {
                $placeholder   = $result[1];
                $isTranslation = false;

                if (strpos($placeholder, 'i18n_') > -1) {
                    $placeholder   = substr($placeholder, 5);
                    $isTranslation = true;
                }

                if ($placeholder == "boats") {
                    $boats = '';
                    if ($boat_data) {
                        foreach ($boat_data["boatTemplates"] as $boatObj) {
                            $boats .= $boatObj;
                        }
                    } else {
                        $boats = '<script type="text/javascript" src="' . plugins_url('shiplisting/public/js/shiplisting-public.js',
                                'shiplisting') . '"></script>';
                    }

                    return $boats;
                }

                if ($placeholder == "pagination") {
                    return $pagination['html'];
                }

                if ($placeholder == "filtering_enabled") {
                    if ( ! empty(Shiplisting_Shortcodes::$api->currentRoute['adv_filter']) && $plugin_data->{'filtering_advanced'} == "1") {
                        return " adv-filtering";
                    } else {
                        return "";
                    }
                }

                if ($placeholder == "filtering" && ! empty(Shiplisting_Shortcodes::$api->currentRoute['adv_filter'])) {
                    if ($source != "") {
                        $template = "";
                        if ($source == "0") {
                            $template = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/shiplisting/public/js/templates/template-yachtall-filtering.html');
                        } elseif ($source == "1") {
                            $template = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/shiplisting/public/js/templates/template-happycharter-filtering.html');
                        }

                        $template = preg_replace_callback("/\{(.*?)\}/i",
                            function ($filter_result) use ($boat_data, $boatTemplate, $pagination, $source) {
                                $placeholder   = $filter_result[1];
                                $isTranslation = false;

                                if (strpos($placeholder, 'i18n_') > -1) {
                                    $placeholder   = substr($placeholder, 5);
                                    $isTranslation = true;
                                }

                                if ($placeholder == "filtering_html") {
                                    return Shiplisting_Shortcodes::$api->ajax_get_defined_filters(Shiplisting_Shortcodes::$api->currentRoute['adv_filter']);
                                }

                                $api_path = explode('-', $placeholder);
                                if ($isTranslation && self::$translation) {
                                    return array_get_value(self::$translation, $api_path);
                                }

                                return array_get_value($boat_data, $api_path);
                            }, $template);

                        return $template;
                    }
                }

                $api_path = explode('-', $placeholder);
                if ($isTranslation) {
                    return array_get_value(self::$translation, $api_path);
                }

                return array_get_value($boat_data, $api_path);
            }, $content);
    }

    public static function display_boats($template, $boatTemplate, $filterId, $hitsByPage, $source)
    {
        $template = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/shiplisting/public/js/templates/' . $template);
        if ( ! $template) {
            return;
        }

        $template = self::replace_placeholder_boats($boatTemplate, $template, $filterId, $hitsByPage, $source);

        if (strpos($template, 'doubleValue(') !== false) {
            return '';
        }

        return $template;
    }

    private static function strposX($haystack, $needle, $number)
    {
        if ($number == '1') {
            return strpos($haystack, $needle);
        } elseif ($number > '1') {
            return strpos($haystack, $needle, self::strposX($haystack, $needle, $number - 1) + strlen($needle));
        } else {
            return "";
        }
    }

    private static function uriReplacePageNumber($newPageNumber)
    {
        $currentLink = $_SERVER["REQUEST_URI"];
        if (stripos($currentLink, '?page=') > 0) {
            $currentLink = str_replace('?page=' . $_GET['page'], '?page=' . $newPageNumber, $currentLink);
        } else {
            if (substr($currentLink, -1, 1) !== '/') {
                $currentLink .= '/';
            }
            $currentLink = $currentLink . '?page=' . $newPageNumber;
        }

        return $currentLink;
    }
}

function array_get_value(array &$array, $parents, $glue = '.')
{
    if ( ! is_array($parents)) {
        $parents = explode($glue, $parents);
    }

    $ref = &$array;

    foreach ((array)$parents as $parent) {
        if (is_array($ref) && array_key_exists($parent, $ref)) {
            $ref = &$ref[$parent];
        } else {
            return null;
        }
    }

    return $ref;
}