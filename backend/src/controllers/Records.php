<?php

namespace Controllers;

require '../vendor/autoload.php';

use \Slim\Http\Request as Request;
use \Slim\Http\Response as Response;

class Records
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
        $records = new \Operations\Records($this->c);

        $paging = new \Utils\PagingInfo($req->getQueryParam('page'), $req->getQueryParam('pagesize'));
        $domain = $req->getQueryParam('domain');
        $queryName = $req->getQueryParam('queryName');
        $type = $req->getQueryParam('type');
        $queryContent = $req->getQueryParam('queryContent');
        $sort = $req->getQueryParam('sort');

        $userId = $req->getAttribute('userId');

        $results = $records->getRecords($paging, $userId, $domain, $queryName, $type, $queryContent, $sort);

        return $res->withJson([
            'paging' => $paging->toArray(),
            'results' => $results
        ], 200);
    }

    public function postNew(Request $req, Response $res, array $args)
    {
        $body = $req->getParsedBody();

        if (!array_key_exists('name', $body) ||
            !array_key_exists('type', $body) ||
            !array_key_exists('content', $body) ||
            !array_key_exists('priority', $body) ||
            !array_key_exists('ttl', $body) ||
            !array_key_exists('ptr', $body) ||
            !array_key_exists('domain', $body)) {
            $this->logger->debug('One of the required fields is missing');
            return $res->withJson(['error' => 'One of the required fields is missing'], 422);
        }

        $userId = $req->getAttribute('userId');
        $ac = new \Operations\AccessControl($this->c);
        if (!$ac->canAccessDomain($userId, $body['domain'])) {
            $this->logger->info('User tries to add record for domain without permission.');
            return $res->withJson(['error' => 'You have no permissions for the given domain.'], 403);
        }

        $records = new \Operations\Records($this->c);

        // Check if CNAME already exists for this name
        try {
            $record = $records->findRecord($body['name'], 'CNAME', null, null, null, $body['domain']);
            // Not OK
            $this->logger->debug('User tries to add CNAME where CNAME exist.', ['name' => $body['name'], 'content' => $body['content']]);
            return $res->withJson(['error' => 'CNAME already exists.'], 400);
        } catch (\Exceptions\NotFoundException $e) {
            // OK
        } catch (\Exceptions\AmbiguousException $e) {
            // Not OK
            $this->logger->debug('User tries to add CNAME where multiple CNAME exist.', ['name' => $body['name'], 'content' => $body['content']]);
            return $res->withJson(['error' => 'Multiple CNAME already exist.'], 400);
        }


        try {
            $result = $records->addRecord($body['name'], $body['type'], $body['content'], $body['priority'], $body['ttl'], $body['domain']);
        } catch (\Exceptions\NotFoundException $e) {
            $this->logger->debug('User tries to add record for invalid domain.');
            return $res->withJson(['error' => 'The domain does not exist or is neighter MASTER nor NATIVE.'], 404);
        } catch (\Exceptions\SemanticException $e) {
            $this->logger->debug('User tries to add record with invalid type.', ['type' => $body['type']]);
            return $res->withJson(['error' => 'The provided type is invalid.'], 400);
        }
        $this->c['logging']->addLog(
            $body['domain'],
            $userId,
            'ADD: #' . $result['id'] . ' ' . $result['name'] . ' ' . $result['type'] . ' ' . $result['content']
        );

        if (!$body['ptr']) {
            return $res->withJson($result, 201);
        }

        // Search for reverse zone of A or AAAA IP address
        if ($result['type'] == 'A' || $result['type'] == 'AAAA') {
            $reverse = $records->getBestMatchingZoneIdFromRecord($userId, $body['type'], $body['content']);

            // Reverse zone could not be found
            if ($reverse == null) {
                return $res->withJson($result, 201);
            }

            // Reverse zone was found and permissions are ensured: Add or update PTR record
            try {
                // Search for specific PTR record first
                $record = $records->findRecord($reverse['arpa'], 'PTR', null, null, null, $reverse['id']);
            } catch (\Exceptions\NotFoundException $e) {
                // PTR record does not exist, create it
                $rresult = $records->addRecord($reverse['arpa'], 'PTR', $body['name'], $body['priority'], $body['ttl'], $reverse['id']);
                $this->c['logging']->addLog(
                    $reverse['id'],
                    $userId,
                    'RADD: #' . $rresult['id'] . ' ' . $rresult['name'] . $rresult['type'] . $rresult['content']
                );
            } catch (\Exceptions\AmbiguousException $e) {
                // Multiple matching records found, give up
                return $res->withJson($result, 201);
            }

            // Single PTR record exists, update it
            if (isset($record)) {
                $rresult = $records->updateRecord($record['id'], $reverse['arpa'], 'PTR', $body['name'], $body['priority'], $body['ttl']);
                $line = '';
                $check = array('name', 'type', 'content', 'priority', 'ttl');
                foreach ($check as $item) {
                    if ($rresult['old'][$item] != $rresult['new'][$item]) {
                        $line .= $item . ': "' . $rresult['old'][$item] . '"->"' . $rresult['new'][$item] . '" ';
                    }
                }
                $this->c['logging']->addLog(
                    $rresult['old']['domain'],
                    $userId,
                    'RUPD: #' . $rresult['old']['id'] . ' ' . $rresult['old']['name'] . ' ' . $line
                );
            }
        }

        return $res->withJson($result, 201);
    }

    public function delete(Request $req, Response $res, array $args)
    {
        $userId = $req->getAttribute('userId');
        $recordId = intval($args['recordId']);
        $ac = new \Operations\AccessControl($this->c);
        if (!$ac->canAccessRecord($userId, $recordId)) {
            $this->logger->info('User tries to delete record without permissions.');
            return $res->withJson(['error' => 'You have no permission to delete this record'], 403);
        }

        $records = new \Operations\Records($this->c);

        try {
            $result = $records->deleteRecord($recordId);
        } catch (\Exceptions\NotFoundException $e) {
            return $res->withJson(['error' => 'No record found for id ' . $recordId], 404);
        }
        $this->c['logging']->addLog(
            $result['domain'],
            $userId,
            'DEL: #' . $result['id'] . ' ' . $result['name'] . ' ' . $result['type'] . ' ' . $result['content']
        );

        $this->logger->info('Deleted record', ['id' => $recordId]);

        // Search for reverse zone of A or AAAA IP address
        if ($result['type'] == 'A' || $result['type'] == 'AAAA') {
            $reverse = $records->getBestMatchingZoneIdFromRecord($userId, $result['type'], $result['content']);

            // Reverse zone could not be found
            if ($reverse == null) {
                return $res->withStatus(204);
            }

            // Reverse zone was found and permissions are ensured: Add or update PTR record
            try {
                // Search for specific PTR record first
                $record = $records->findRecord($reverse['arpa'], 'PTR', null, null, null, $reverse['id']);
            } catch (\Exceptions\NotFoundException $e) {
                // Record does not exist, we're done
                return $res->withStatus(204);
            } catch (\Exceptions\AmbiguousException $e) {
                // Multiple matching records found, give up
                return $res->withStatus(204);
            }

            // Single PTR record exists, delete it
            if (isset($record)) {
                $rresult = $records->deleteRecord($record['id']);
                $this->c['logging']->addLog(
                    $rresult['domain'],
                    $userId,
                    'RDEL: #' . $rresult['id'] . ' ' . $rresult['name'] . ' ' . $rresult['type'] . ' ' . $rresult['content']
                );
            }
        }

        return $res->withStatus(204);
    }

    public function getSingle(Request $req, Response $res, array $args)
    {
        $userId = $req->getAttribute('userId');
        $recordId = intval($args['recordId']);

        $ac = new \Operations\AccessControl($this->c);
        if (!$ac->canAccessRecord($userId, $recordId)) {
            $this->logger->info('Non admin user tries to get record without permission.');
            return $res->withJson(['error' => 'You have no permissions for this record.'], 403);
        }

        $records = new \Operations\Records($this->c);

        try {
            $result = $records->getRecord($recordId);

            $this->logger->debug('Get record info', ['id' => $recordId]);
            return $res->withJson($result, 200);
        } catch (\Exceptions\NotFoundException $e) {
            return $res->withJson(['error' => 'No record found for id ' . $recordId], 404);
        }
    }

    public function put(Request $req, Response $res, array $args)
    {
        $userId = $req->getAttribute('userId');
        $recordId = intval($args['recordId']);

        $ac = new \Operations\AccessControl($this->c);
        if (!$ac->canAccessRecord($userId, $recordId)) {
            $this->logger->info('Non admin user tries to update record without permission.');
            return $res->withJson(['error' => 'You have no permissions for this record.'], 403);
        }

        $body = $req->getParsedBody();

        $name = array_key_exists('name', $body) ? $body['name'] : null;
        $type = array_key_exists('type', $body) ? $body['type'] : null;
        $content = array_key_exists('content', $body) ? $body['content'] : null;
        $priority = array_key_exists('priority', $body) ? $body['priority'] : null;
        $ttl = array_key_exists('ttl', $body) ? $body['ttl'] : null;
        $ptr = array_key_exists('ptr', $body) ? $body['ptr'] : null;

        $records = new \Operations\Records($this->c);

        try {
            $result = $records->updateRecord($recordId, $name, $type, $content, $priority, $ttl);
        } catch (\Exceptions\NotFoundException $e) {
            $this->logger->debug('User tries to update not existing record.');
            return $res->withJson(['error' => 'The record does not exist.'], 404);
        } catch (\Exceptions\SemanticException $e) {
            $this->logger->debug('User tries to update record with invalid type.', ['type' => $type]);
            return $res->withJson(['error' => 'The provided type is invalid.'], 400);
        }
        $line = '';
        $check = array('name', 'type', 'content', 'priority', 'ttl');
        foreach ($check as $item) {
            if ($result['old'][$item] != $result['new'][$item]) {
                $line .= $item . ': "' . $result['old'][$item] . '"->"' . $result['new'][$item] . '" ';
            }
        }
        $this->c['logging']->addLog(
            $result['old']['domain'],
            $userId,
            'UPD: #' . $result['old']['id'] . ' ' . $result['old']['name'] . ' ' . $line
        );

        if (!$ptr) {
            return $res->withStatus(204);
        }

        // Search for old reverse zone of A or AAAA IP address
        if ($result['old']['type'] == 'A' || $result['old']['type'] == 'AAAA') {
            $reverse_old = $records->getBestMatchingZoneIdFromRecord($userId, $result['old']['type'], $result['old']['content']);

            if ($reverse_old != null) {
                try {
                    // Search for old PTR record
                    $record = $records->findRecord($reverse_old['arpa'], 'PTR', null, null, null, $reverse_old['id']);
                } catch (\Exceptions\NotFoundException $e) {
                    // Record does not exist, okay
                } catch (\Exceptions\AmbiguousException $e) {
                    // Multiple matching records found, give up
                }
            }
        }

        // Search for new reverse zone of A or AAAA IP address
        if ($result['new']['type'] == 'A' || $result['new']['type'] == 'AAAA') {
            $reverse = $records->getBestMatchingZoneIdFromRecord($userId, $result['new']['type'], $result['new']['content']);

            // PTR record exists?
            if (isset($record)) {
                // Reverse zone changed?
                if ($reverse != $reverse_old) {
                    // Delete the old PTR record
                    $rresult = $records->deleteRecord($record['id']);
                    $this->c['logging']->addLog(
                        $rresult['domain'],
                        $userId,
                        'RDEL: #' . $rresult['id'] . ' ' . $rresult['name'] . ' ' . $rresult['type'] . ' ' . $rresult['content']
                    );

                    // New reverse zone exists?
                    if ($reverse != null) {
                        // Create a new PTR record in the new reverse zone
                        $rresult = $records->addRecord($reverse['arpa'], 'PTR', $result['new']['name'], $result['new']['priority'], $result['new']['ttl'], $reverse['id']);
                        $this->c['logging']->addLog(
                            $reverse['id'],
                            $userId,
                            'RADD: #' . $rresult['id'] . ' ' . $rresult['name'] . $rresult['type'] . $rresult['content']
                        );
                        return $res->withStatus(204);
                    }
                } else {
                    // Reverse zone stayed the same, update existing PTR record
                    $rresult = $records->updateRecord($record['id'], $reverse['arpa'], 'PTR', $result['new']['name'], $result['new']['priority'], $result['new']['ttl']);
                    $line = '';
                    $check = array('name', 'type', 'content', 'priority', 'ttl');
                    foreach ($check as $item) {
                        if ($rresult['old'][$item] != $rresult['new'][$item]) {
                            $line .= $item . ': "' . $rresult['old'][$item] . '"->"' . $rresult['new'][$item] . '" ';
                        }
                    }
                    $this->c['logging']->addLog(
                        $rresult['old']['domain'],
                        $userId,
                        'RUPD: #' . $rresult['old']['id'] . ' ' . $rresult['old']['name'] . ' ' . $line
                    );
                    return $res->withStatus(204);
                }
            } elseif ($reverse == null) {
                // New reverse zone doesn't exist either, done
                return $res->withStatus(204);
            }

            // Old reverse zone doesn't exist but new one does

            try {
                // Search for PTR record in the new reverse zone
                $record = $records->findRecord($reverse['arpa'], 'PTR', null, null, null, $reverse['id']);
            } catch (\Exceptions\NotFoundException $e) {
                // PTR record does not exist, create it
                $rresult = $records->addRecord($reverse['arpa'], 'PTR', $result['new']['name'], $result['new']['priority'], $result['new']['ttl'], $reverse['id']);
                $this->c['logging']->addLog(
                    $reverse['id'],
                    $userId,
                    'RADD: #' . $rresult['id'] . ' ' . $rresult['name'] . $rresult['type'] . $rresult['content']
                );
                return $res->withStatus(204);
            } catch (\Exceptions\AmbiguousException $e) {
                // Multiple matching records found, give up
                return $res->withStatus(204);
            }

            // Found PTR record in new zone, update it
            if (isset($record)) {
                $rresult = $records->updateRecord($record['id'], $reverse['arpa'], 'PTR', $result['new']['name'], $result['new']['priority'], $result['new']['ttl']);
                $line = '';
                $check = array('name', 'type', 'content', 'priority', 'ttl');
                foreach ($check as $item) {
                    if ($rresult['old'][$item] != $rresult['new'][$item]) {
                        $line .= $item . ': "' . $rresult['old'][$item] . '"->"' . $rresult['new'][$item] . '" ';
                    }
                }
                $this->c['logging']->addLog(
                    $rresult['old']['domain'],
                    $userId,
                    'RUPD: #' . $rresult['old']['id'] . ' ' . $rresult['old']['name'] . ' ' . $line
                );
            }
        } elseif (isset($record)) {
            // Old reverse record exists but new record is not of type A or AAAA
            // Delete the old reverse record
            $rresult = $records->deleteRecord($record['id']);
            $this->c['logging']->addLog(
                $rresult['domain'],
                $userId,
                'RDEL: #' . $rresult['id'] . ' ' . $rresult['name'] . ' ' . $rresult['type'] . ' ' . $rresult['content']
            );
        }
    }
}
