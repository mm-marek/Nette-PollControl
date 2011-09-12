<?php

use Nette\Object;
use Nette\Environment;

/**
 * PollControlModelImpl - part of PollControl plugin for Nette Framework for voting.
 *
 * @copyright  Copyright (c) 2009 Ondřej Brejla
 * @license    New BSD License
 * @link       http://github.com/OndrejBrejla/Nette-PollControl
 * @package    Nette\Extras
 * @version    0.1
 */
class PollControlModelImpl extends Object implements PollControlModel {

    const SESSION_NAMESPACE = '__poll_control';

    /**
     * Connection to the database.
     *
     * @var NotORM Connection to the database.
     */
    private $connection;

    /**
     * Id of the current poll.
     *
     * @var mixed Id of the current poll.
     */
    private $id;

    /**
     * Constructor of the poll control model layer.
     *
     * @param mixed $id Id of the current poll.
     * @param NotORM $database
     */
    public function __construct($id, \NotORM $database) {
        $this->id = $id;
        $this->connection = $database;

        $sess = Environment::getSession(self::SESSION_NAMESPACE);
        $sess->poll[$id] = FALSE;
    }

    /**
     * @see PollControlModel::getAllVotesCount()
     */
    public function getAllVotesCount() {
        $some = $this->connection->poll_control_answers('questionId', $this->id)->select('SUM(votes) AS votes')->fetch();
       // \Nette\Diagnostics\Debugger::barDump($some);
        return $some['votes'];
    }

    /**
     * @see PollControlModel::getQuestion()
     */
    public function getQuestion() {
        $question = $this->connection->poll_control_questions('id', $this->id)->select('question')->fetch();
        return $question["question"];
    }

    /**
     * @see PollControlModel::getAnswers()
     */
    public function getAnswers() {
        //$this->connection->fetchAll('SELECT id, answer, votes FROM poll_control_answers WHERE questionId = %i', $this->id);

        $answers = array();
        foreach ($this->connection->poll_control_answers('questionId', $this->id)->select('id, answer, votes') as $row) {
            $answers[] = new PollControlAnswer($row['answer'], $row['id'], $row['votes']);
        }

        return $answers;
    }

    /**
     * Makes vote for specified answer id.
     *
     * @param int $id Id of specified answer.
     */
    public function vote($id) {
        if ($this->isVotable()) {
            $this->connection->poll_control_answers(
                    array(
                        'id' => $id,
                        'questionId' => $this->id
                    ))
                    ->update(array('votes' => new NotORM_Literal('votes + 1')));
            
            $this->denyVotingForUser();
        } else {
            throw new BadRequestException('You can vote only once per hour.');
        }
    }

    /**
     * @see PollControlModel::isVotable()
     */
    public function isVotable() {
        $sess = Environment::getSession(self::SESSION_NAMESPACE);

        if ($sess->poll[$this->id] === TRUE) {
            return FALSE;
        } else {
            if ($this->connection->poll_control_votes(array(
                        'ip' => $_SERVER['REMOTE_ADDR'],
                        'questionId' => $this->id
                    ))
                    ->where(new NotORM_Literal("date + INTERVAL 30 SECOND > NOW()"))
                    ->count('*')) {
                return FALSE;
            }
        }

        return TRUE;
    }

    /**
     * Disables voting for the user who had currently voted.
     */
    private function denyVotingForUser() {
        $sess = Environment::getSession(self::SESSION_NAMESPACE);

        $sess->poll[$this->id] = TRUE;

        $this->connection->poll_control_votes()->insert(array(
            "questionId" => $this->id,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'date' => new NotORM_Literal('NOW()')
        ));
    }

}