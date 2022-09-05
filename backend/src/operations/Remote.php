<?php

namespace Operations;

require '../vendor/autoload.php';

/**
 * This class provides functions for the remote api.
 */
class Remote
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
     * Update given record with password
     *
     * @param   $record     Record to update
     * @param   $content    New content
     * @param   $disabled   New disabled
     * @param   $password   Password to authenticate
     *
     * @throws  NotFoundException   if the record does not exist
     * @throws  ForbiddenException  if the password is not valid for the record
     */
    public function updatePassword(int $record, string $content, ? bool $disabled, string $password) : void
    {
        $query = $this->db->prepare('SELECT id FROM records WHERE id=:record');
        $query->bindValue(':record', $record, \PDO::PARAM_INT);
        $query->execute();

        if ($query->fetch() === false) {
            throw new \Exceptions\NotFoundException();
        }

        $query = $this->db->prepare('SELECT security FROM remote WHERE record=:record AND type=\'password\'');
        $query->bindValue(':record', $record, \PDO::PARAM_INT);
        $query->execute();

        $validPwFound = false;

        while ($row = $query->fetch()) {
            if (password_verify($password, $row['security'])) {
                $validPwFound = true;
                break;
            }
        }

        if (!$validPwFound) {
            throw new \Exceptions\ForbiddenException();
        }

        $records = new \Operations\Records($this->c);
        $records->updateRecord($record, null, null, $content, null, null, $disabled);
    }

    /**
     * Update given record with signature
     *
     * @param   $record     Record to update
     * @param   $content    New content
     * @param   $disabled   New disabled
     * @param   $time       Timestamp of the signature
     * @param   $signature  Signature
     *
     * @throws  NotFoundException   if the record does not exist
     * @throws  ForbiddenException  if the signature is not valid for the record
     */
    public function updateKey(int $record, string $content, ? bool $disabled, int $time, string $signature) : void
    {
        $timestampWindow = $this->c['config']['remote']['timestampWindow'];

        $query = $this->db->prepare('SELECT id FROM records WHERE id=:record');
        $query->bindValue(':record', $record, \PDO::PARAM_INT);
        $query->execute();

        if ($query->fetch() === false) {
            throw new \Exceptions\NotFoundException();
        }

        $query = $this->db->prepare('SELECT security FROM remote WHERE record=:record AND type=\'key\'');
        $query->bindValue(':record', $record, \PDO::PARAM_INT);
        $query->execute();

        if (abs($time - time()) > $timestampWindow) {
            throw new \Exceptions\ForbiddenException();
        }

        $validKeyFound = false;

        $verifyString = $record . $content . $time;
        if ($disabled !== null) {
            $verifyString = $record . $content . intval($disabled) . $time;
        }

        $this->logger->info($verifyString);

        while ($row = $query->fetch()) {
            if (openssl_verify($verifyString, base64_decode($signature), $row['security'], OPENSSL_ALGO_SHA512)) {
                $validKeyFound = true;
                break;
            }
        }

        if (!$validKeyFound) {
            throw new \Exceptions\ForbiddenException();
        }

        $records = new \Operations\Records($this->c);
        $records->updateRecord($record, null, null, $content, null, null, $disabled);
    }
}
