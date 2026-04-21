<?php

require_once __DIR__."/../../Database.php";

class Repository {

    protected $database;

    public function __construct() {
        $this->database = new Database();
    }

    protected function withUserContext(string $userId, callable $fn): mixed
    {
        $this->database->beginTransaction();
        try {
            $this->database->setUserContext($userId);
            $result = $fn();
            $this->database->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->database->rollback();
            throw $e;
        }
    }
}