<?php

namespace Oara\Network\Publisher;
use DateTime;
use Oara\Network;
use Oara\Utilities;
use ZipArchive;
use function base64_encode;
use function count;
use function dirname;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function preg_match;
use function realpath;
use function str_getcsv;
use function stream_context_create;
use function unlink;
use function urlencode;

/**
 * The goal of the Open Affiliate Report Aggregator (OARA) is to develop a set
 * of PHP classes that can download affiliate reports from a number of affiliate networks, and store the data in a common format.
 *
 * Copyright (C) 2016  Fubra Limited
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Contact
 * ------------
 * Fubra Limited <support@fubra.com> , +44 (0)1252 367 200
 **/

/**
 * API Class
 *
 * @author     Carlos Morillo Merino
 * @category   As
 * @copyright  Fubra Limited
 * @version    Release: 01.00
 *
 */
class GoogleAndroidPublisher extends Network
{

    /**
     * @var null
     */
    private $_bucket = null;

    /**
     * @param $credentials
     */
    public function login($credentials)
    {
        $this->_bucket = $credentials["user"];
        $this->_httpLogin = $credentials['httpLogin'];
    }

    /**
     * @return array
     */
    public function getNeededCredentials()
    {
        $credentials = [];

        $parameter = [];
        $parameter["description"] = "Bucket";
        $parameter["required"] = true;
        $parameter["name"] = "Bucket";
        $credentials["bucket"] = $parameter;

        $parameter = [];
        $parameter["description"] = "Password for the bucket";
        $parameter["required"] = true;
        $parameter["name"] = "Password";
        $credentials["password"] = $parameter;

        return $credentials;
    }

    /**
     * @return bool
     */
    public function checkConnection()
    {
        $connection = false;

        $url = "http://affjet.dc.fubra.net/tools/gsutil/gs.php?bucket=" . urlencode($this->_bucket) . "&type=ls";
        $context = stream_context_create([
            'http' => [
                'header' => "Authorization: Basic " . base64_encode("{$this->_httpLogin}")
            ]
        ]);


        $return = file_get_contents($url, false, $context);
        if (preg_match("/ls works/", $return)) {
            $connection = true;
        }
        return $connection;
    }

    /**
     * @return array
     */
    public function getMerchantList()
    {
        $merchants = [];

        $obj = [];
        $obj['cid'] = 1;
        $obj['name'] = "Google Android Publisher";
        $obj['url'] = "www.google.com";
        $merchants[] = $obj;

        return $merchants;
    }

    /**
     * @param null $merchantList
     * @param DateTime|null $dStartDate
     * @param DateTime|null $dEndDate
     * @return array
     */
    public function getTransactionList($merchantList = null, DateTime $dStartDate = null, DateTime $dEndDate = null)
    {
        $totalTransactions = [];

        $dirDestination = realpath(dirname(COOKIES_BASE_DIR)) . '/pdf';

        $file = "{$this->_bucket}/sales/salesreport_" . $dStartDate->format("Ym") . ".zip";
        $url = "http://affjet.dc.fubra.net/tools/gsutil/gs.php?bucket=" . urlencode($file) . "&type=cp";

        $context = stream_context_create([
            'http' => [
                'header' => "Authorization: Basic " . base64_encode("{$this->_httpLogin}")
            ]
        ]);

        file_put_contents($dirDestination . "/report.zip", file_get_contents($url, false, $context));

        $zip = new ZipArchive;
        if ($zip->open($dirDestination . "/report.zip") === TRUE) {
            $zip->extractTo($dirDestination);
            $zip->close();
        } else {
            return $totalTransactions;
        }
        unlink($dirDestination . "/report.zip");
        $salesReport = file_get_contents($dirDestination . "/salesreport_" . $dStartDate->format("Ym") . ".csv");
        $salesReport = explode("\n", $salesReport);
        for ($i = 1; $i < count($salesReport) - 1; $i++) {

            $row = str_getcsv($salesReport[$i], ",");
            $sub = false;
            if ($row[12] < 0) {
                $sub = true;
            }
            $obj = [];
            $obj['unique_id'] = $row[0] . $row[3];
            $obj['merchantId'] = "1";
            $obj['date'] = $row[1] . " 00:00:00";
            $obj['custom_id'] = $row[5];
            $comission = 0.3;
            if ($row[6] == "com.petrolprices.app") {
                $value = 2.99;
                $obj['amount'] = Utilities::parseDouble($value);
                $obj['commission'] = Utilities::parseDouble($value - ($value * $comission));
            } elseif ($row[6] == "com.fubra.wac") {
                if ($obj['date'] < "2013-04-23 00:00:00") {
                    $value = 0.69;
                    $obj['amount'] = Utilities::parseDouble($value);
                    $obj['commission'] = Utilities::parseDouble($value - ($value * $comission));
                } else {
                    $value = 1.49;
                    $obj['amount'] = Utilities::parseDouble($value);
                    $obj['commission'] = Utilities::parseDouble($value - ($value * $comission));
                }
            }

            if ($sub) {
                $obj['amount'] = -$obj['amount'];
                $obj['commission'] = -$obj['commission'];
            }

            $obj['status'] = Utilities::STATUS_CONFIRMED;

            $totalTransactions[] = $obj;
        }
        unlink($dirDestination . "/salesreport_" . $dStartDate->format("Ym") . ".csv");
        return $totalTransactions;
    }
}
