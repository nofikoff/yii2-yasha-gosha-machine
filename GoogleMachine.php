<?php
/**
 * by Novikov.ua 2016
 */


namespace nofikoff\yashagosha;

//use Yii;

use app\controllers\SystemMessagesLogController;
use app\controllers\TestController;
use nofikoff\proxybrowser\ProxyBrowser;

class GoogleMachine
{


    public $result = [
        'error' => 0,
        'description' => '',
        'date' => false,
        'result' => 0,
        'code' => 0,
    ];
    public $gdomain = 'google.ru';
    public $glang = 'ru';
    public $gnearcity = '';
    public $debug = false;


    // режим ТОЧНОЕ СООТВЕТСВИЕ в кавычкаъ
    public $exactly = false;
    // мобильная версия поисковика (прост как идея)
    public $mobile_version = false;
    // мнять БЕСПОЛЕЗНО гугл выкупает
    public $noJs = true;

    // максимальная длина запроса в словах
    public $query_max_length = 30;

    // задержка между обращениями в ГУгл
    public $timeout_sec = 1; // обычно 3, но есл ииспоьлзовать тор прокси, то и так тупит ответка

    public $use_antigate = 1;
    public $use_my_external_php_proxy = 0;

    public $file_cookies = './_logs/_google_cookies.txt';

    // читаем результат запроса в поисковике
    //$strParams= 'tbm=isch'; - поиск по картинкам
    public function get_page($strReq, $strParams = '')
    {


        $this->result['error'] = false;
        $this->result['description'] = '';
        $this->result['result'] = '';

        if (!trim($strReq)) {
            $this->result['error'] = true;
            $this->result['description'] = 'ERROR. Пустой запрос';
            return $this->result;
        }


        $browser = new ProxyBrowser();

        $browser->erase_js = true;
        $browser->use_antigate = $this->use_antigate;
        $browser->use_my_external_php_proxy = $this->use_my_external_php_proxy;
        $browser->file_cookies = $this->file_cookies;
        $browser->timeout = $this->timeout_sec;
        $browser->timewaitconnect = 30;
        $browser->interface_lang = 'en';

        if ($strParams) $strParams = '&' . $strParams;
        $a = $browser->get_http($this->get_url_request($strReq) . $strParams);

        if (!$a['error']) $this->result['result'] = $a['raw'];
        else echo $a['description'];

        if ($this->debug OR 1) {
            SystemMessagesLogController::Save(
            //0 просто мессадж серым
            //1 ключевое сообщение зеленым
            //2 красный варанинг
            //3 красный ЖИРНЫМ фатал
                0,
                "GoogleMachine get_page",
                "Просто накапливаем выдачу поисковиков Гугл ",
                $a['raw']
            );
        }

        if (preg_match('/To continue, please type the characters below/i', $this->result['result'])) {
            $this->result['error'] = true;
            $this->result['description'] = 'ERROR. Каптча в выдаче';
        }

        //
        return $this->result;

    }

    // читаем результат запроса в поисковике
    // выводит грязный хтмл кэша конкретной страницы
    // и ее дату в массиве
    public function get_cache($url)
    {

        $url = ProxyBrowser::url_http_cutting($url);

        $this->result['url'] = $url;

        if (!trim($url)) {
            $this->result['error'] = true;
            $this->result['description'] = 'ERROR. Пустаой запрос';
            return $this->result;
        }

        $browser = new ProxyBrowser();
        $browser->use_antigate = $this->use_antigate;
        $browser->file_cookies = $this->file_cookies;

        $browser->erase_js = true;
        $browser->timeout = $this->timeout_sec;
        $browser->interface_lang = 'en';
        $a = $browser->get_http('http://webcache.googleusercontent.com/search?q=cache:' . $url . '&hl=' . $browser->interface_lang);


        $this->result['result'] = $a['raw'];
        $this->result['code'] = $a['code'];

        SystemMessagesLogController::Save(
        //0 просто мессадж серым
        //1 ключевое сообщение зеленым
        //2 красный варанинг
        //3 красный ЖИРНЫМ фатал
            0,
            "Google get_cache",
            "Просто накапливаем выдачу поисковиков Гугл ",
            $this->result['result']
        );

        if (preg_match('/was not found on this server\.\s+<ins>That’s all we know\.<\/ins>/i', $this->result['result'])) {
            $this->result['error'] = true;
            $this->result['description'] = 'ERROR. No cache';
        } else if (!preg_match('/page as it appeared on (.*?)\./i', $this->result['result'], $d)) {
            $this->result['error'] = true;
            $this->result['description'] = 'ERROR. Не вижу даты кэша - значит кэшбитый';
        } else {
            $this->result['date'] = date('Y-m-d', strtotime($d[1]));
            // дату определили
            // выкидывем первую строку с вставкой гугл
            $this->result['result'] = preg_replace('/^.+\n/', '', $this->result['result']);
            $this->result['result'] = preg_replace('/^.+\n/', '', $this->result['result']);
        }

        if (preg_match('/To continue, please type the characters below/i', $this->result['result'])) {
            $this->result['error'] = true;
            $this->result['description'] = 'ERROR. Каптча в выдаче';
        }


        //
        return $this->result;

    }


    // формирует адресную строку гугла с нужными апарметрами и доменом
    public function get_url_request($strReq)
    {
        $strReq = trim($strReq);
        if ($this->exactly) {
            $strReq = '"' . $strReq . '"';
        }
        $strUrl = 'https://www.' . $this->gdomain . '/search?q=' . urlencode($strReq);
        if ($this->noJs) $strUrl .= '&gbv=1';
        return $strUrl;

    }


    // парсим количество найденых страниц из выдач гугла
    // на входе грязный хтмл твета гугл
    public function parse_number_finded_results($strResponce)
    {

        $this->result['error'] = false;
        $this->result['description'] = '';
        $this->result['result'] = '';

//        Результатов: 2

        if (
            preg_match('/did not match any documents/i', $strResponce)
            OR
            preg_match('/ничего не найдено/ui', $strResponce)
        )
            $this->result['result'] = 0;

        else if (
            preg_match('/No results found for/', $strResponce)
            OR
            preg_match('/Нет результатов для/ui', $strResponce)
        )
            $this->result['result'] = 0;

        else if (
            preg_match('/>About ([0-9, ]+) results</', $strResponce, $d)
            OR
            preg_match('/>Результатов: примерно\s([0-9, ]+)</ui', $strResponce, $d)
            OR
            preg_match('/>Результатов примерно\s([0-9, ]+)</ui', $strResponce, $d)
        )
            $this->result['result'] = $d[1];

        else if (
            preg_match('/>([0-9, ]+) results</', $strResponce, $d)
            OR
            preg_match('/>Результатов:\s([0-9, ]+)</ui', $strResponce, $d)
        )
            $this->result['result'] = $d[1];
        else if (
        preg_match('/>([0-9, ]+) result</', $strResponce, $d)
//            OR
//            preg_match('/>([0-9, ]+) result</', $strResponce, $d)
        )
            $this->result['result'] = $d[1];

        else {
            $this->result['error'] = true;
            $this->result['description'] = 'ERROR Парсинг страниы Не могу распарсить страницу гугла на предмет количества резульататов';
        }


        $this->result['result'] = str_replace(",", "", $this->result['result']);
        $this->result['result'] = str_replace(" ", "", $this->result['result']);

        /*
                print_r($this->result);
                echo $strResponce;*/


        return $this->result;
    }


    // возвращает массив с возврастом кэша в ГУгл
    function google_page_index_age($url)
    {
        $this->result['url'] = $url;
        $r = $this->get_cache($url);

        //
        if ($r['date']) {
            $this->result['age'] = floor((time() - strtotime($r['date'])) / (60 * 60 * 24));
            $this->result['result'] = 1;
            $this->result['error'] = 0;
            $this->result['description'] = 'Страница в индексе Гугла';
            $this->result['date'] = $r['date'];

        } else if ($r['code'] == '404') {
            SystemMessagesLogController::Save(
            //0 просто мессадж серым
            //1 ключевое сообщение зеленым
            //2 красный варанинг
            //3 красный ЖИРНЫМ фатал
                2,
                "google_indexed",
                " Страница донора не в Индексе Гугла " . $url
            );
            $this->result['result'] = 0;
            $this->result['age'] = -1;
            $this->result['error'] = 0;
            $this->result['description'] = 'Страница донора НЕ в Индексе Гугла';

        } else {

            $this->result['result'] = 0;
            $this->result['age'] = -1;
            $this->result['error'] = 1;
            $this->result['description'] = 'Какой то аппаратный сбой, возможно капча и пр ' . $r['description'];

            SystemMessagesLogController::Save(
            //0 просто мессадж серым
            //1 ключевое сообщение зеленым
            //2 красный варанинг
            //3 красный ЖИРНЫМ фатал
                3,
                "google_indexed",
                $this->result['description'] . $url
            );

        }

        return $this->result;
    }


    // возвращает размер индекса проекта по гуглу
    function google_domain_sizeindex($domain)
    {

        //для порядку
        $this->result['url'] = $domain;
        // готовим запрос в гугл
        $domain = ProxyBrowser::url_http_cutting(ProxyBrowser::domain_from_url($domain));
        $r = $this->parse_number_finded_results($this->get_page('site:' . $domain)['result']);
        //
        if ($r['result']) {
            $this->result['result'] = $r['result'];
            $this->result['error'] = 0;
            $this->result['description'] = 'Донор имеет страницы в индексе Гугла';

        } else if ($r['result'] == 0) {
            SystemMessagesLogController::Save(
            //0 просто мессадж серым
            //1 ключевое сообщение зеленым
            //2 красный варанинг
            //3 красный ЖИРНЫМ фатал
                2,
                "google_domain_hasindex",
                " Донор НЕ имеет страниц в Индексе Гугла " . $domain
            );
            $this->result['result'] = 0;
            $this->result['error'] = 0;
            $this->result['description'] = 'Страница донора НЕ в Индексе Гугла';

        } else {

            $this->result['result'] = 0;
            $this->result['error'] = 1;
            $this->result['description'] = 'Какой то аппаратный сбой, возможно капча и пр ' . $r['description'];

            SystemMessagesLogController::Save(
            //0 просто мессадж серым
            //1 ключевое сообщение зеленым
            //2 красный варанинг
            //3 красный ЖИРНЫМ фатал
                3,
                "google_domain_sizeindex",
                $this->result['description'] . $domain
            );

        }

        return $this->result;
    }



}
