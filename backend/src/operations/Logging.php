<?php

namespace Operations;

use function Monolog\Handler\error_log;

require '../vendor/autoload.php';

/**
 * This class provides functions for retrieving and adding log lines.
 */
class Logging
{
    /** @var \Monolog\Logger */
    private $logger;

    /** @var \PDO */
    private $db;

    /** @var \Slim\Container */
    private $c;

    public function __construct(\Slim\Container $c)
    {
        $this->logger = $c->logger;
        $this->db = $c->db;
        $this->c = $c;
    }

    /**
     * Get a list of records according to filter criteria
     * 
     * @param   $pi             PageInfo object, which is also updated with total page number
     * @param   $userId         Id of the user for which the table should be retrieved
     * @param   $domain         Comma separated list of domain ids
     * @param   $user           Comma separated list of user ids
     * @param   $sort           Sort string in format 'field-asc,field2-desc', null for default
     * 
     * @return  array           Array with matching records
     */
    public function getLogs(
        \Utils\PagingInfo &$pi,
        int $userId,
        ? string $domain,
        ? string $log,
        ? string $user,
        ? string $sort
    ) : array {
        $ac = new \Operations\AccessControl($this->c);
        $userIsAdmin = $ac->isAdmin($userId);

        $domain = $domain === null ? '%' : '%' . $domain . '%';
        $user = $user === null ? '%' : '%' . $user . '%';
        $log = $log === null ? '%' : '%' . $log . '%';

        $setDomains = \Services\Database::makeSetString($this->db, $domain);
        $setUsers = \Services\Database::makeSetString($this->db, $user);
        $setLog = \Services\Database::makeSetString($this->db, $log);

        //Count elements
        if ($pi->pageSize === null) {
            $pi->totalPages = 1;
        } else {
            $query = $this->db->prepare('
                SELECT COUNT(*) AS total FROM logging L
                LEFT OUTER JOIN domains D ON L.domain_id = D.id
                LEFT OUTER JOIN users U ON L.user_id = U.id
                LEFT OUTER JOIN permissions P ON P.domain_id = L.domain_id
                WHERE (P.user_id=:userId OR :userIsAdmin) AND
                (D.name LIKE ' . $setDomains . ' OR :noDomainFilter) AND
                (L.log LIKE ' . $setLog . ' OR :noLogFilter) AND
                (U.name LIKE ' . $setUsers . ' OR :noUserFilter)
            ');

            $query->bindValue(':userId', $userId, \PDO::PARAM_INT);
            $query->bindValue(':userIsAdmin', intval($userIsAdmin), \PDO::PARAM_INT);
            $query->bindValue(':noDomainFilter', intval($domain === null), \PDO::PARAM_INT);
            $query->bindValue(':noLogFilter', intval($log === null), \PDO::PARAM_INT);
            $query->bindValue(':noUserFilter', intval($user === null), \PDO::PARAM_INT);

            $query->execute();
            $record = $query->fetch();

            $pi->totalPages = ceil($record['total'] / $pi->pageSize);
        }

        //Query and return result
        $ordStr = \Services\Database::makeSortingString($sort, [
            'id' => 'L.id'
        ]);
        $pageStr = \Services\Database::makePagingString($pi);

        $query = $this->db->prepare('
            SELECT L.id, L.domain_id as domain, D.name as domain_name, L.user_id as user, U.name as user_name, L.timestamp, L.log FROM logging L
            LEFT OUTER JOIN domains D ON L.domain_id = D.id
            LEFT OUTER JOIN users U ON L.user_id = U.id
            LEFT OUTER JOIN permissions P ON P.domain_id = L.domain_id
            WHERE (P.user_id=:userId OR :userIsAdmin) AND
            (D.name LIKE ' . $setDomains . ' OR :noDomainFilter) AND
            (L.log LIKE ' . $setLog . ' OR :noLogFilter) AND
            (U.name LIKE ' . $setUsers . ' OR :noUserFilter)
            GROUP BY L.id' . $ordStr . $pageStr);

        $query->bindValue(':userId', $userId, \PDO::PARAM_INT);
        $query->bindValue(':userIsAdmin', intval($userIsAdmin), \PDO::PARAM_INT);
        $query->bindValue(':noDomainFilter', intval($domain === null), \PDO::PARAM_INT);
        $query->bindValue(':noLogFilter', intval($log === null), \PDO::PARAM_INT);
        $query->bindValue(':noUserFilter', intval($user === null), \PDO::PARAM_INT);

        $query->execute();

        $data = $query->fetchAll();

        return array_map(function ($item) {
            $item['id'] = intval($item['id']);
            $item['timestamp'] = $item['timestamp'];
            $item['domain'] = intval($item['domain']);
            $item['domain_name'] = $item['domain_name'];
            $item['user'] = intval($item['user']);
            $item['user_name'] = $item['user_name'];
            return $item;
        }, $data);
    }

    /**
     * Add new log entry
     * 
     * @param   $domain     Domain id of the affected domain
     * @param   $user       User id of the executing user
     * @param   $log        Log message
     * 
     * @return  array       New log entry
     * 
     * @throws  NotFoundException   if the domain or user does not exist
     */
    public function addLog(int $domain, int $user, string $log) : array
    {
        $query = $this->db->prepare('SELECT id FROM domains WHERE id=:id AND type IN (\'MASTER\',\'NATIVE\')');
        $query->bindValue(':id', $domain, \PDO::PARAM_INT);
        $query->execute();
        if ($query->fetch() === false) { // Domain does not exist
            throw new \Exceptions\NotFoundException();
        }

        $query = $this->db->prepare('SELECT id FROM users WHERE id=:id');
        $query->bindValue(':id', $user, \PDO::PARAM_INT);
        $query->execute();
        if ($query->fetch() === false) { // User does not exist
            throw new \Exceptions\NotFoundException();
        }

        $this->db->beginTransaction();

        $query = $this->db->prepare('INSERT INTO logging (domain_id, user_id, timestamp, log)
                                    VALUES (:domainId, :userId, NOW(), :log)');
        $query->bindValue(':domainId', $domain, \PDO::PARAM_INT);
        $query->bindValue(':userId', $user, \PDO::PARAM_INT);
        $query->bindValue(':log', $log, \PDO::PARAM_STR);
        $query->execute();

        $insertId = $this->db->lastInsertId();

        $this->db->commit();

        return $this->getLog($insertId);
    }

    /**
     * Delete log entry
     * 
     * @param   $id     Id of the log to delete
     * 
     * @return  array   Deleted log entry
     * 
     * @throws  NotFoundException   if log does not exist
     */
    public function deleteLog(int $id) : array
    {
        $log = $this->getLog($id);

        $this->db->beginTransaction();

        $query = $this->db->prepare('DELETE FROM logging WHERE id=:id');
        $query->bindValue(':id', $id, \PDO::PARAM_INT);
        $query->execute();

        $this->db->commit();

        return $log;
    }

    /**
     * Get log entry
     * 
     * @param   $od         Id of the log
     * 
     * @return  array       Log entry
     * 
     * @throws  NotFoundException   if the log does not exist
     */
    public function getLog(int $id) : array
    {
        $query = $this->db->prepare('SELECT id, domain_id as domain, user_id as user, timestamp, log FROM logging
                                     WHERE id=:id');
        $query->bindValue(':id', $id, \PDO::PARAM_INT);
        $query->execute();

        $log = $query->fetch();

        if ($log === false) {
            throw new \Exceptions\NotFoundException();
        }

        $log['id'] = intval($log['id']);
        $log['domain'] = intval($log['domain']);
        $log['user'] = intval($log['user']);

        return $log;
    }
}
