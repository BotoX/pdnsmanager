<?php

namespace Controllers;

require '../vendor/autoload.php';

use \Slim\Http\Request as Request;
use \Slim\Http\Response as Response;

class Logging
{
    /** @var \Monolog\Logger */
    private $logger;

    /** @var \Slim\Container */
    private $c;

    public function __construct(\Slim\Container $c)
    {
        $this->logger = $c->logger;
        $this->c = $c;
    }

    public function getList(Request $req, Response $res, array $args)
    {
        $logging = new \Operations\Logging($this->c);

        $paging = new \Utils\PagingInfo($req->getQueryParam('page'), $req->getQueryParam('pagesize'));
        $domain = $req->getQueryParam('domain');
        $log = $req->getQueryParam('log');
        $user = $req->getQueryParam('user');
        $sort = $req->getQueryParam('sort');

        $userId = $req->getAttribute('userId');

        $results = $logging->getLogs($paging, $userId, $domain, $log, $user, $sort);

        return $res->withJson([
            'paging' => $paging->toArray(),
            'results' => $results
        ], 200);
    }
}
