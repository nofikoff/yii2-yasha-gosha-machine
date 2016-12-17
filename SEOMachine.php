<?php
/**
 * by Novikov.ua 2016
 */


namespace nofikoff\yashagosha;

use Yii;

use app\controllers\SystemMessagesLogController;
use nofikoff\proxybrowser\ProxyBrowser;

class SEOMachine
{

    public $antigate_key_API;
    public $result =
        [
            'result' => 0,
            'error' => 1,
            'description' => '',
            'code' => '',
            'url' => '',
        ];


    function __construct()
    {
        $this->antigate_key_API = Yii::$app->get('settings')->get('system.AntigateAPIKey');

    }


    /**
     * MozRank и Page Authority страниц
     * Majestic SEO Citation Flow и Trust Flow
     * Ahrefs URL Rating и Domain Raiting
     *
     * MozRank минимум 2
     * Citation Flow минимцм 10
     * Page Authority минимум 12
     * Trust Rank по MozRank
     * Исправлена проверка метрик Moz.com.
     * Для возможности определения MozRank и Page Authority страниц необходимо зарегистрироваться на сайте moz.com, получить Access ID и Secret Key и указать их в настройках панели.
     * Бесплатное API позволяет делать до 25 тысяч запросов в месяц.
     *
     */
    public function getMoz()
    {

    }

    /**
     * Majestic - Citation Flow
     * Trust Flow
     */
    function MajesticSeo($url, $mode = 'url')
    {
        // ВАЖНО $res <> "" - это если нулевое значени евозвращается
        // если без HTTP тоэта функция будет выдавть отчетыи типа РУТДОМЕН
        if ($mode == 'url')
            $url = ProxyBrowser::url_http_adding($url);
        else
            $url = ProxyBrowser::domain_from_url($url);

        $sak = Yii::$app->get('settings')->get('system.MajesticSEO_API');

        $browser = new ProxyBrowser();
        $browser->interface_lang = 'en';
        $browser->use_my_external_php_proxy = true;
        $uri = 'http://simpleapi.majesticseo.com/sapi/GetBacklinkStats?datasource=fresh&items=1&item0=' . urlencode($url) . '&sak=' . $sak . '';
        $out = $browser->get_http($uri);

        if ($out['error']) {
            $out['description'] .= " Скорей всего слетел API Key MajesticSeo или IP заблокировали : " . $out['raw'];
            SystemMessagesLogController::Save(
            //0 просто мессадж серым
            //1 ключевое сообщение зеленым
            //2 красный варанинг
            //3 красный ЖИРНЫМ фатал
                3,
                "MajesticSeo_forURL",
                $out['description']
            );
//            echo "<textarea>";
            print_r($out);
//            echo "</textarea>";

        }
        $out['result_array'] = json_decode($out['raw'], true);
        $out['result_array'] = $out['result_array']['Data'][0];
        // расчитаем траст по Кокшарову Деваке
        $out['result_array']['KTrustRank'] = $this->MajesticSeo_forURL_KokshkarovTrustRank($out['result_array']['CitationFlow'], $out['result_array']['TrustFlow']);
        $out['result'] = true;
        return $out;
    }

    function MajesticSeo_forURL_KokshkarovTrustRank($CF, $TF)
    {
        // по формуле Кошкарова
        $TrustRank = ($TF - $CF) / ($TF + $CF + 1) + $TF / ($CF + 1) + $TF / 100;
        $TrustRank = $TrustRank ? substr($TrustRank, 0, 4) : -1;
        return $TrustRank;

    }

    function CheckTrustRu($url, $mode = 'domain') // сервси в принципе только омены проверяет
    {

        // если без HTTP тоэта функция будет выдавть отчетыи типа РУТДОМЕН
        if ($mode == 'url')
            $url = ProxyBrowser::url_http_adding($url);
        else
            $url = ProxyBrowser::domain_from_url($url);

        echo "<h4>".$url." CheckTrustRu</h4>";

        $browser = new ProxyBrowser();
        $browser->interface_lang = 'en';
        // тут не надо $browser->use_my_external_php_proxy = true;
        $uri = 'http://checktrust.ru/app.php?r=host/app/summary/basic&applicationKey=' . Yii::$app->get('settings')->get('system.CheckTrustRuAPI') . '&parameterList=spam,trust&host=' . urlencode($url);
        $out = $browser->get_http($uri);

        if (isset($out['raw'])) $out['result_array'] = @json_decode($out['raw'], true);


        if ($out['error'] OR !$out['result_array']['success']) {


            $out['error'] = 1;
            $out['description'] .= " - {$out['result_array']['message']} - СЕРВИС ТУПИТ CheckTrustRuAPI : " . $out['raw'];

            if ($out['code'] != '200') {
                SystemMessagesLogController::Save(
                //0 просто мессадж серым
                //1 ключевое сообщение зеленым
                //2 красный варанинг
                //3 красный ЖИРНЫМ фатал
                    2,
                    "CheckTrustRu",
                    'СУдя по всему нашатнулся сервер CheckTrustRu'
                );

            } else if ($out['result_array']['hostLimitsBalance'] < 10) {
                SystemMessagesLogController::Save(
                //0 просто мессадж серым
                //1 ключевое сообщение зеленым
                //2 красный варанинг
                //3 красный ЖИРНЫМ фатал
                    3,
                    "CheckTrustRu",
                    $out['description']
                );
            }
            //            echo "<textarea>";
            print_r($out);
            //            echo "</textarea>";

        }
        //
        $out['result'] = true;
        return $out;
    }
}
