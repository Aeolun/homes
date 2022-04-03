<?php
/**
 * Created by PhpStorm.
 * User: aeolu
 * Date: 9/2/2018
 * Time: 10:16 AM
 */

namespace Bart\Homes\Command;

//https://suumo.jp/jj/bukken/ichiran/JJ010FJ001/?ar=030&bs=030&ta=10&jspIdFlg=patternShikugun&kb=1&kt=9999999&km=1&tb=0&tt=9999999&ekTjCd=&ekTjNm=&tj=0&kj=9
//https://suumo.jp/jj/bukken/ichiran/JJ010FJ001/?ar=030&bs=030&ta=08&jspIdFlg=patternShikugun&kb=1&kt=9999999&km=1&tb=0&tt=9999999&ekTjCd=&ekTjNm=&tj=0&kj=9
//https://suumo.jp/jj/bukken/ichiran/JJ010FJ001/?ar=030&bs=030&ta=12&jspIdFlg=patternShikugun&kb=1&kt=9999999&km=1&tb=0&tt=9999999&ekTjCd=&ekTjNm=&tj=0&kj=9
//https://suumo.jp/jj/bukken/ichiran/JJ010FJ001/?ar=020&bs=030&ta=06&jspIdFlg=patternShikugun&kb=1&kt=9999999&km=1&tb=0&tt=9999999&ekTjCd=&ekTjNm=&tj=0&kj=9
//https://suumo.jp/jj/bukken/ichiran/JJ010FJ001/?ar=020&bs=030&ta=03&jspIdFlg=patternShikugun&kb=1&kt=9999999&km=1&tb=0&tt=9999999&ekTjCd=&ekTjNm=&tj=0&kj=9
//https://suumo.jp/jj/bukken/ichiran/JJ010FJ001/?ar=070&bs=030&ta=36&jspIdFlg=patternShikugun&kb=1&kt=9999999&km=1&tb=0&tt=9999999&ekTjCd=&ekTjNm=&tj=0&kj=9
//https://suumo.jp/jj/bukken/ichiran/JJ010FJ001/?ar=070&bs=030&ta=38&jspIdFlg=patternShikugun&kb=1&kt=9999999&km=1&tb=0&tt=9999999&ekTjCd=&ekTjNm=&tj=0&kj=9
//


use Dotenv\Dotenv;
use GuzzleHttp\Client;
use Medoo\Medoo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RetrieveCommand extends Command
{
    private $types = [
        '030' => 'Land',
        '021' => 'Second-hand house',
        '020' => 'New house',
        '011' => 'Second-hand mansion',
        '010' => 'New mansion'
    ];

    private $areas = [
        '010' => [
            'name' => 'Hokkaido',
            'provinces' => [
                '01' => 'Hokkaido',
            ]
        ],
        '020' => [
            'name' => 'Tohoku',
            'provinces' => [
                '02' => 'Aomori',
                '03' => 'Iwate',
                '04' => 'Miyagi',
                '05' => 'Akita',
                '06' => 'Yamagata',
                '07' => 'Fukushima'
            ]
        ],
        '030' => [
            'name' => 'Kanto',
            'provinces' => [
                '08' => 'Ibaraki',
                '09' => 'Tochigi',
                '10' => 'Gunma',
                '11' => 'Saitama',
                '12' => 'Chiba',
                '13' => 'Tokyo',
                '14' => 'Kanagawa'
            ]
        ],
        '040' => [
            'name' => 'Hokuriku',
            'provinces' => [
                '15' => 'Niigata',
                '16' => 'Toyama',
                '17' => 'Ishikawa',
                '18' => 'Fukui',
                '19' => 'Yamanashi',
                '20' => 'Nagano',
            ]
        ],
        '050' => [
            'name' => 'Tokai',
            'provinces' => [
                '21' => 'Gifu',
                '22' => 'Shizuoka',
                '23' => 'Aichi',
                '24' => 'Mie',
            ]
        ],
        '060' => [
            'name' => 'Kansai',
            'provinces' => [
                '25' => 'Shiga',
                '26' => 'Kyoto',
                '27' => 'Osaka',
                '28' => 'Hyogo',
                '29' => 'Nara',
                '30' => 'Wakayama'
            ]
        ],
        '080' => [
            'name' => 'Chugoku',
            'provinces' => [
                '31' => 'Tottori',
                '32' => 'Shimane',
                '33' => 'Okayama',
                '34' => 'Hiroshima',
                '35' => 'Yamaguchi'
            ]
        ],
        '070' => [
            'name' => 'Shikoku',
            'provinces' => [
                '36' => 'Tokushima',
                '37' => 'Kagawa',
                '38' => 'Ehime',
                '39' => 'Kochi',
            ]
        ],
        '090' => [
            'name' => 'Kyushu',
            'provinces' => [
                '40' => 'Fukuoka',
                '41' => 'Saga',
                '42' => 'Nagasaki',
                '43' => 'Kumamoto',
                '44' => 'Oita',
                '45' => 'Miyazaki',
                '46' => 'Kagoshima',
                '47' => 'Okinawa'
            ]
        ]
    ];


    private $base = 'https://suumo.jp/jj/bukken/ichiran/JJ012FC001/?ar={area}&bs={type}&ta={province}&pn={page}&ekTjCd=&ekTjNm=&kb=1&kj=9&km=1&kt=9999999&ta=13&tb=0&tj=0&tt=9999999&po=0&pj=1&pc=100';
    private Medoo|null $database = null;
    private Client|null $client = null;
    private $perPage = 100;

    private $existingIds = [];

    private $fields = [
        'id' => './/input[@name="bsnc"]/@value',
        'jsId' => './/input[@class="js-clipkey"]/@value',
        'value' => './/dt[text()[contains(.,\'販売価格\')]]/../dd/span',
        'area' => './/dt[text()[contains(.,\'土地面積\')]]/../dd',
        'address' => './/dt[text()[contains(.,\'所在地\')]]/../dd',
        'station' => './/dt[text()[contains(.,\'沿線・駅\')]]/../dd',
    ];

    private $optionalFields = [
        'building_area' => './/dt[text()[contains(.,\'建物面積\')]]/../dd',
        'type' => './/dt[text()[contains(.,\'間取り\')]]/../dd',
        'coverage' => './/dt[text()[contains(.,\'建ぺい率・容積率\')]]/../dd'
    ];

    protected function configure()
    {
        $this->setName('retrieve')
            ->addOption('region', 'r', InputOption::VALUE_REQUIRED, 'The number for the region to retrieve')
            ->addOption('province', 'p', InputOption::VALUE_REQUIRED, 'The number for the province to retrieve')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'The kind of items to retrieve')
            ->setDescription('Retrieves data from homes and stores to the database.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->safeLoad();

        $necessaryVars = ['DATABASE_TYPE', 'DATABASE_NAME', 'DATABASE_HOST', 'DATABASE_USERNAME', 'DATABASE_PASSWORD', 'DATABASE_CHARSET'];
        foreach($necessaryVars as $var) {
            if (!isset($_ENV[$var])) {
                throw new \Exception("Need the environment variable ${$var} to be set.");
            }
        }

        $this->database = new Medoo([
            'database_type' => $_ENV["DATABASE_TYPE"],
            'database' => $_ENV["DATABASE_NAME"],
            'host' => $_ENV["DATABASE_HOST"],
            'username' => $_ENV["DATABASE_USERNAME"],
            'password' => $_ENV["DATABASE_PASSWORD"],
            'charset' => $_ENV["DATABASE_CHARSET"],
        ]);
        $this->client = new Client();

        $everything = $this->database->select('property', ['suumo_id']);
        foreach ($everything as $row) {
            $this->existingIds[$row['suumo_id']] = true;
        }

        $onlyRegion = $input->getOption('region');
        $onlyProvince = $input->getOption('province');

        $type = $input->getOption('type') ?? '030';

        foreach ($this->areas as $key => $area) {
            if ($onlyRegion && $key !== $onlyRegion) continue;
            foreach ($area['provinces'] as $provinceId => $provinceName) {
                if ($onlyProvince && $provinceId != $onlyProvince) continue;
                $page = $this->getPage('list', $this->base, 1, $key, $provinceId, $type);
                $totalItems = $this->getTotalItems($page);
                try {
                    $this->parseItems($page, $output, $area['name'], $provinceName, $type);
                } catch (\Exception $e) {
                    throw new \Exception("Exception on page 1: " . $e->getMessage());
                }

                $pagesToLoad = ceil($totalItems / $this->perPage);
                $output->writeln("Loaded page 1/$pagesToLoad for " . $provinceName . " in " . $area['name']);

                if ($totalItems) {
                    for ($i = 2; $i <= $pagesToLoad; $i++) {
                        $start = microtime(true);
                        $page = $this->getPage('list', $this->base, $i, $key, $provinceId, $type);
                        try {
                            $this->parseItems($page, $output, $area['name'], $provinceName, $type);
                        } catch (\Exception $e) {
                            throw new \Exception("Exception on page " . $i . ": " . $e->getMessage());
                        }
                        $output->writeln("Loaded page $i/$pagesToLoad for " . $provinceName . " in " . $area['name'] . ' taking ' . round(microtime(true) - $start, 3) . 's, memory used: ' . round(memory_get_usage(true) / 1024 / 1024, 3) . ' MB, pagesize ' . round(strlen($page) / 1024 / 1024, 3) . ' MB');
                    }
                }
                $output->writeln("Done loading items for $provinceName.");
            }
        }
    }

    private function parseItems($body, OutputInterface $output, $areaName, $provinceName, $type)
    {
        $dom = new \DOMDocument();
        @$dom->loadHtml($body);

        $xml = simplexml_import_dom($dom);

        $items = $xml->xpath("//div[contains(@class, 'property_unit ')]");
        $allItems = [];
        foreach ($items as $item) {
            try {
                $id = $this->getElement($item, './/input[@name="bsnc"]/@value');
                $data = $this->parseItem($item);
                if (!isset($this->existingIds[$data['suumo_id']])) {
                    $data['province'] = $provinceName;
		    $data['region'] = $areaName;
		    $data['property_type'] = $type;
                    $allItems[] = $data;
                }
            } catch (\Exception $e) {
                $output->writeln("Exception parsing item " . $id . ": " . $e->getMessage());
            }
        }

        $inserted = $this->database->insert('property', $allItems);
    }

    private function getElement($xml, $xpath)
    {
        $el = $xml->xpath($xpath);
        if (count($el) > 0) {
            return (string)$el[0];
        }
        return null;
    }

    private function parseItem($xml)
    {
        $id = $this->getElement($xml, './/input[@name="bsnc"]/@value');
        if (isset($this->existingIds[$id])) {
            return [
                'suumo_id' => $id
            ];
        }

        $field = [];
        foreach ($this->fields as $key => $value) {
            $result = $this->getElement($xml, $value);
            if (!$result) {
                throw new \Exception("Can not find field: " . $key);
            }
            $field[$key] = $result;
        }

        foreach ($this->optionalFields as $key => $value) {
            $result = $this->getElement($xml, $value);
            if ($result) {
                $field[$key] = $result;
            }
        }

        $url = $this->getElement($xml, './/h2/a/@href');

        $landCoverage = 0;
        $volume = 0;
        if (isset($field['coverage']) && $field['coverage']) {
            $coverMatch = preg_match('/建ペい率：([0-9]+)/u', $field['coverage'], $matches);
            if ($coverMatch) {
                $landCoverage = $matches[1];
            }
            $coverMatch = preg_match('/容積率：([0-9]+)/', $field['coverage'], $matches);
            if ($coverMatch) {
                $volume = $matches[1];
            }
            if (strpos($field['coverage'], '・' && !$landCoverage && !$volume) !== false) {
                list($landCoverage, $volume) = explode('・', $field['coverage']);
            }
        }

//        $res = $this->client->post('https://libpostal.apps.cluster.serial-experiments.com/parser', [
//            'body' => json_encode([
//                'query' => $field['address']
//            ])
//        ]);
//        $parsed = json_decode($res->getBody()->getContents(), true);
//        $parsedAddress = [];
//        foreach ($parsed as $item) {
//            $parsedAddress[$item['label']] = $item['value'];
//        }

        $station = $this->parseStation(trim($field['station']));

        $data = [
            'suumo_id' => $field['id'],
            'suumo_js_id' => $field['jsId'],
            'price' => $this->parsePrice($field['value']),
            'area' => $this->parseArea($field['area']),
            'building_area' => isset($field['building_area']) ? $this->parseArea($field['building_area']) : null,
            'address' => $field['address'],
            'type' => isset($field['type']) ? $field['type'] : null,
            'coverage' => $landCoverage,
            'volume' => $volume,
            'url' => 'https://suumo.jp' . $url,
//            'state' => $parsedAddress['state'],
//            'city' => $parsedAddress['city'],
            'train_line' => $station['line'],
            'train_station' => $station['station'],
            'station_distance_foot' => $station['foot']
        ];

        return $data;
    }

    private function parseStation($station)
    {
        //東京メトロ南北線「王子神谷」徒歩8分

        preg_match('/([^「]+)「([^」]+)」/u', $station, $matches);
        $line = $matches[1];
        $stationName = $matches[2];

        $byFoot = 0;
        $match = preg_match('/徒歩([0-9]+)分/u', $station, $matches);
        if ($match) {
            $byFoot = $matches[1];
        }

        return [
            'line' => $line,
            'station' => $stationName,
            'foot' => $byFoot
        ];
    }

    private function parseArea($area)
    {
        if ($area) {
            if (strpos($area, '㎡') !== false) {
                return substr($area, 0, strpos($area, '㎡'));
            }
            return substr($area, 0, strpos($area, 'm'));
        } else {
            return 0;
        }
    }

    private function parsePrice($price)
    {
        $split = mb_strpos($price, '～');
        if ($split !== false) {
            $price = mb_substr($price, 0, $split - 1);
        }
        $split = mb_strpos($price, '・');
        if ($split !== false) {
            $price = mb_substr($price, 0, $split - 1);
        }

        preg_match('/([0-9])億/u', $price, $oku);
        preg_match('/([0-9]+)万/u', $price, $man);
        preg_match('/万([0-9])/u', $price, $yen);

        $price = 0;
        if (isset($oku[1]) && $oku[1] != '') {
            $price += $oku[1] * 10000 * 10000;
        }
        if (isset($man[1]) && $man[1] != '') {
            $price += $man[1] * 10000;
        }
        if (isset($yen[1]) && $yen[1] != '') {
            $price += $yen[1];
        }

        return intval($price);
    }

    private function getPage($kind, $url, $pageNr, $area, $province, $type)
    {
        $url = str_replace('{area}', $area, $url);
        $url = str_replace('{type}', $type, $url);
        $url = str_replace('{page}', $pageNr, $url);
        $url = str_replace('{province}', $province, $url);
        $existing = $this->database->get('page', [
            'content'
        ], [
            'url' => $url
        ]);
        if ($existing) {
            return $existing['content'];
        } else {
            $page = $this->client->get($url, [
                'headers' => [
                    'Connection' => 'keep-alive',
                    'Cache-Control' => 'max-age=0',
                    'Upgrade-Insecure-Requests' => '1',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.106 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                    'Referer' => 'https://www.homes.co.jp/tochi/tokyo/list/',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Accept-Language' => 'en-US,en;q=0.9,nl;q=0.8,ja;q=0.7'
                ]
            ]);
            usleep(mt_rand(1000, 3000) * 1000);
            $body = $page->getBody()->getContents();
            $this->database->insert('page', [
                'url' => $url,
                'kind' => $kind,
                'content' => $body
            ]);
            return $body;
        }
    }

    private function getTotalItems($page)
    {
        $dom = new \DOMDocument();
        @$dom->loadHtml($page);

        $xml = simplexml_import_dom($dom);

        $element = $xml->xpath('//div[@class="pagination_set-hit"]');
        if ($element) {
            return intval(str_replace(',', '', trim($element[0])));
        } else {
            return null;
        }
    }
}
