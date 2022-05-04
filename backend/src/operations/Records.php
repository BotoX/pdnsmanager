<?php

namespace Operations;

use function Monolog\Handler\error_log;

require '../vendor/autoload.php';

/**
 * This class provides functions for retrieving and modifying domains.
 */
class Records
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
     * @param   $queryName      Search query to search in the record name, null for no filter
     * @param   $type           Comma separated list of types
     * @param   $queryContent   Search query to search in the record content, null for no filter
     * @param   $sort           Sort string in format 'field-asc,field2-desc', null for default
     * 
     * @return  array           Array with matching records
     */
    public function getRecords(
        \Utils\PagingInfo &$pi,
        int $userId,
        ? string $domain,
        ? string $queryName,
        ? string $type,
        ? string $queryContent,
        ? string $sort
    ) : array {
        $this->db->beginTransaction();

        $ac = new \Operations\AccessControl($this->c);
        $userIsAdmin = $ac->isAdmin($userId);

        $queryName = $queryName === null ? '%' : '%' . $queryName . '%';
        $queryContent = $queryContent === null ? '%' : '%' . $queryContent . '%';

        $setDomains = \Services\Database::makeSetString($this->db, $domain);
        $setTypes = \Services\Database::makeSetString($this->db, $type);

        //Count elements
        if ($pi->pageSize === null) {
            $pi->totalPages = 1;
        } else {
            $query = $this->db->prepare('
                SELECT COUNT(*) AS total FROM records R
                LEFT OUTER JOIN domains D ON R.domain_id = D.id
                LEFT OUTER JOIN permissions P ON P.domain_id = R.domain_id
                WHERE (P.user_id=:userId OR :userIsAdmin) AND
                (R.domain_id IN ' . $setDomains . ' OR :noDomainFilter) AND
                (R.name LIKE :queryName) AND
                (R.type IN ' . $setTypes . ' OR :noTypeFilter) AND
                (R.content LIKE :queryContent) AND
                R.type <> \'SOA\'
            ');

            $query->bindValue(':userId', $userId, \PDO::PARAM_INT);
            $query->bindValue(':userIsAdmin', intval($userIsAdmin), \PDO::PARAM_INT);
            $query->bindValue(':queryName', $queryName, \PDO::PARAM_STR);
            $query->bindValue(':queryContent', $queryContent, \PDO::PARAM_STR);
            $query->bindValue(':noDomainFilter', intval($domain === null), \PDO::PARAM_INT);
            $query->bindValue(':noTypeFilter', intval($type === null), \PDO::PARAM_INT);

            $query->execute();
            $record = $query->fetch();

            $pi->totalPages = ceil($record['total'] / $pi->pageSize);
        }

        //Query and return result
        $ordStr = \Services\Database::makeSortingString($sort, [
            'id' => 'R.id',
            'name' => 'R.name',
            'type' => 'R.type',
            'content' => 'R.content',
            'priority' => 'R.prio',
            'ttl' => 'R.ttl'
        ]);
        $pageStr = \Services\Database::makePagingString($pi);

        $query = $this->db->prepare('
            SELECT R.id,R.name,R.type,R.content,R.prio as priority,R.ttl,R.domain_id as domain FROM records R
            LEFT OUTER JOIN domains D ON R.domain_id = D.id
            LEFT OUTER JOIN permissions P ON P.domain_id = R.domain_id
            WHERE (P.user_id=:userId OR :userIsAdmin) AND
            (R.domain_id IN ' . $setDomains . ' OR :noDomainFilter) AND
            (R.name LIKE :queryName) AND
            (R.type IN ' . $setTypes . ' OR :noTypeFilter) AND
            (R.content LIKE :queryContent)  AND
            R.type <> \'SOA\'
            GROUP BY R.id' . $ordStr . $pageStr);

        $query->bindValue(':userId', $userId, \PDO::PARAM_INT);
        $query->bindValue(':userIsAdmin', intval($userIsAdmin), \PDO::PARAM_INT);
        $query->bindValue(':queryName', $queryName, \PDO::PARAM_STR);
        $query->bindValue(':queryContent', $queryContent, \PDO::PARAM_STR);
        $query->bindValue(':noDomainFilter', intval($domain === null), \PDO::PARAM_INT);
        $query->bindValue(':noTypeFilter', intval($type === null), \PDO::PARAM_INT);

        $query->execute();

        $data = $query->fetchAll();

        $this->db->commit();

        return array_map(function ($item) {
            $item['id'] = intval($item['id']);
            $item['priority'] = intval($item['priority']);
            $item['ttl'] = intval($item['ttl']);
            $item['domain'] = intval($item['domain']);
            return $item;
        }, $data);
    }

    /**
     * Add new record
     * 
     * @param   $name       Name of the new record
     * @param   $type       Type of the new record
     * @param   $content    Content of the new record
     * @param   $priority   Priority of the new record
     * @param   $ttl        TTL of the new record
     * @param   $domain     Domain id of the domain to add the record
     * 
     * @return  array       New record entry
     * 
     * @throws  NotFoundException   if the domain does not exist
     * @throws  SemanticException   if the record type is invalid
     */
    public function addRecord(string $name, string $type, string $content, int $priority, int $ttl, int $domain) : array
    {
        if (!in_array($type, $this->c['config']['records']['allowedTypes'])) {
            throw new \Exceptions\SemanticException();
        }

        $this->db->beginTransaction();

        $query = $this->db->prepare('SELECT id FROM domains WHERE id=:id AND type IN (\'MASTER\',\'NATIVE\')');
        $query->bindValue(':id', $domain, \PDO::PARAM_INT);
        $query->execute();

        $record = $query->fetch();

        if ($record === false) { // Domain does not exist
            $this->db->rollBack();
            throw new \Exceptions\NotFoundException();
        }

        $query = $this->db->prepare('INSERT INTO records (domain_id, name, type, content, ttl, prio)
                                    VALUES (:domainId, :name, :type, :content, :ttl, :prio)');
        $query->bindValue(':domainId', $domain, \PDO::PARAM_INT);
        $query->bindValue(':name', $name, \PDO::PARAM_STR);
        $query->bindValue(':type', $type, \PDO::PARAM_STR);
        $query->bindValue(':content', $content, \PDO::PARAM_STR);
        $query->bindValue(':ttl', $ttl, \PDO::PARAM_INT);
        $query->bindValue(':prio', $priority, \PDO::PARAM_INT);
        $query->execute();

        $insertId = $this->db->lastInsertId();

        $soa = new \Operations\Soa($this->c);
        $soa->updateSerial($domain);

        $this->db->commit();

        return $this->getRecord($insertId);
    }

    /**
     * Delete record
     * 
     * @param   $id     Id of the record to delete
     * 
     * @return  array   Deleted record entry
     * 
     * @throws  NotFoundException   if record does not exist
     */
    public function deleteRecord(int $id) : array
    {
        $record = $this->getRecord($id);

        $this->db->beginTransaction();

        $query = $this->db->prepare('DELETE FROM remote WHERE record=:id');
        $query->bindValue(':id', $id, \PDO::PARAM_INT);
        $query->execute();

        $query = $this->db->prepare('DELETE FROM records WHERE id=:id');
        $query->bindValue(':id', $id, \PDO::PARAM_INT);
        $query->execute();

        $soa = new \Operations\Soa($this->c);
        $soa->updateSerial($record['domain']);

        $this->db->commit();

        return $record;
    }

    /**
     * Get record
     * 
     * @param   $recordId   Name of the record
     * 
     * @return  array       Record entry
     * 
     * @throws  NotFoundException   if the record does not exist
     */
    public function getRecord(int $recordId) : array
    {
        $query = $this->db->prepare('SELECT id,name,type,content,prio AS priority,ttl,domain_id AS domain FROM records
                                     WHERE id=:recordId');
        $query->bindValue(':recordId', $recordId, \PDO::PARAM_INT);
        $query->execute();

        $record = $query->fetch();

        if ($record === false) {
            throw new \Exceptions\NotFoundException();
        }

        $record['id'] = intval($record['id']);
        $record['priority'] = intval($record['priority']);
        $record['ttl'] = intval($record['ttl']);
        $record['domain'] = intval($record['domain']);

        return $record;
    }

    /** Find single record
     * 
     * If params are null do not search for them
     * 
     * @param   $domain     Domain id of the zone to search
     * @param   $name       name
     * @param   $type       type
     * @param   $content    content
     * @param   $priority   priority
     * @param   $ttl        ttl
     * 
     * @return  array       Record entry
     * 
     * @throws  NotFoundException   The given record does not exist
     * @throws  SemanticException   The given record type is invalid
     * @throws  AmbiguousException  if more than one record matches the search
     */
    public function findRecord(? string $name, ? string $type, ? string $content, ? int $priority, ? int $ttl, int $domain, ? int $exceptId = null) : array
    {
        if ($type !== null && !in_array($type, $this->c['config']['records']['allowedTypes'])) {
            throw new \Exceptions\SemanticException();
        }

        $queryStr = 'SELECT id FROM records WHERE domain_id = :domain';
        if ($name !== null) {
            $queryStr .= ' AND name = :name';
        }
        if ($type !== null) {
            $queryStr .= ' AND type = :type';
        }
        if ($content !== null) {
            $queryStr .= ' AND content = :content';
        }
        if ($priority !== null) {
            $queryStr .= ' AND prio = :prio';
        }
        if ($ttl !== null) {
            $queryStr .= ' AND ttl = :ttl';
        }
        if ($exceptId !== null) {
            $queryStr .= ' AND id != :exceptId';
        }

        $query = $this->db->prepare($queryStr);
        $query->bindValue(':domain', $domain, \PDO::PARAM_INT);
        if ($name !== null) {
            $query->bindValue(':name', $name, \PDO::PARAM_STR);
        }
        if ($type !== null) {
            $query->bindValue(':type', $type, \PDO::PARAM_STR);
        }
        if ($content !== null) {
            $query->bindValue(':content', $content, \PDO::PARAM_STR);
        }
        if ($priority !== null) {
            $query->bindValue(':prio', $priority, \PDO::PARAM_INT);
        }
        if ($ttl !== null) {
            $query->bindValue(':ttl', $ttl, \PDO::PARAM_INT);
        }
        if ($exceptId !== null) {
            $query->bindValue(':exceptId', $exceptId, \PDO::PARAM_INT);
        }
        $query->execute();

        $record = $query->fetch();

        if ($record === false) {
            throw new \Exceptions\NotFoundException();
        }

        if ($query->rowCount() > 1) {
            throw new \Exceptions\AmbiguousException();
        }

        return $this->getRecord(intval($record['id']));
    }

    /** Update Record
     * 
     * If params are null do not change
     * 
     * @param   $recordId   Record to update
     * @param   $name       New name
     * @param   $type       New type
     * @param   $content    New content
     * @param   $priority   New priority
     * @param   $ttl        New ttl
     * 
     * @return  array       Record entry
     * 
     * @throws  NotFoundException   The given record does not exist
     * @throws  SemanticException   The given record type is invalid
     */
    public function updateRecord(int $recordId, ? string $name, ? string $type, ? string $content, ? int $priority, ? int $ttl) : array
    {
        if ($type !== null && !in_array($type, $this->c['config']['records']['allowedTypes'])) {
            throw new \Exceptions\SemanticException();
        }

        $record = $this->getRecord($recordId);

        $name = $name === null ? $record['name'] : $name;
        $type = $type === null ? $record['type'] : $type;
        $content = $content === null ? $record['content'] : $content;
        $priority = $priority === null ? intval($record['priority']) : $priority;
        $ttl = $ttl === null ? intval($record['ttl']) : $ttl;

        $this->db->beginTransaction();

        $query = $this->db->prepare('UPDATE records SET name=:name,type=:type,content=:content,ttl=:ttl,prio=:prio
                                    WHERE id=:recordId');
        $query->bindValue(':recordId', $recordId, \PDO::PARAM_INT);
        $query->bindValue(':name', $name, \PDO::PARAM_STR);
        $query->bindValue(':type', $type, \PDO::PARAM_STR);
        $query->bindValue(':content', $content, \PDO::PARAM_STR);
        $query->bindValue(':ttl', $ttl, \PDO::PARAM_INT);
        $query->bindValue(':prio', $priority, \PDO::PARAM_INT);
        $query->execute();

        $soa = new \Operations\Soa($this->c);
        $soa->updateSerial($record['domain']);

        $this->db->commit();

        return array(
            "old" => $record,
            "new" => $this->getRecord($recordId)
        );
    }

    /**
     * Get Best Matching in-addr.arpa Zone ID from A or AAAA record
     * 
     * @param   $userId     Id of the user for which the reverse zone should be searched
     * @param   $type       Record type (A or AAAA)
     * @param   $content    Record content (IPv4 or IPv6 address)
     * 
     * @return  array       Zone ID and PTR record name or null
     * 
     * @throws  NotFoundException   if the zone does not exist
     * @throws  SemanticException   The given record type is invalid
     */
    public function getBestMatchingZoneIdFromRecord(int $userId, string $type, string $content) : ?array
    {
        if ($type == 'A') {
            $in_addr = inet_pton($content);
            if ($in_addr !== false) {
                $arr = preg_split("/\./", inet_ntop($in_addr));
                $arpa = sprintf("%d.%d.%d.%d.in-addr.arpa", $arr[3], $arr[2], $arr[1], $arr[0]);
            }
        } elseif ($type == 'AAAA') {
            $in6_addr = inet_pton($content);
            if ($in6_addr !== false) {
                $hex = unpack('H*hex', $in6_addr)['hex'];
                $arpa = implode('.', array_reverse(str_split($hex))) . '.ip6.arpa';
            }
        } else {
            throw new \Exceptions\SemanticException();
        }

        // Invalid record content (invalid IP), ignore since it's user supplied
        if (!isset($arpa) || $arpa === null) {
            return null;
        }

        $ac = new \Operations\AccessControl($this->c);
        $userIsAdmin = $ac->isAdmin($userId);

        $query = $this->db->prepare('SELECT id, name FROM domains
                                    LEFT OUTER JOIN permissions P ON P.domain_id = id
                                    WHERE (P.user_id=:userId OR :userIsAdmin) AND
                                    name LIKE \'%.arpa\'
                                    ORDER BY length(name) DESC');
        $query->bindValue(':userId', $userId, \PDO::PARAM_INT);
        $query->bindValue(':userIsAdmin', $userIsAdmin, \PDO::PARAM_INT);
        $response = $query->execute();

        if ($response === false) {
            return null;
        }

        $match = 72; // the longest ip6.arpa has a length of 72
        $found_domain_id = -1;
        while ($r = $query->fetch()) {
            $pos = stripos($arpa, $r["name"]);
            if ($pos !== false) {
                // one possible searched $arpa is found
                if ($pos < $match) {
                    $match = $pos;
                    $found_domain_id = $r["id"];
                }
            }
        }

        if ($found_domain_id == -1) {
            return null;
        }

        return array("id" => $found_domain_id, "arpa" => $arpa);
    }
}
