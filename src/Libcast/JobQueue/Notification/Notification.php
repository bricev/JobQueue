<?php

/*
 * This file is part of Libcast JobQueue component.
 *
 * (c) Brice Vercoustre <brcvrcstr@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Libcast\JobQueue\Notification;

use Libcast\JobQueue\Exception\NotificationException;

class Notification implements \Serializable
{
    const TYPE_ERROR   = 'error';

    const TYPE_SUCCESS = 'success';

    /**
     * @var \Swift_Mailer
     */
    protected $mailer;

    /**
     * @var array
     */
    protected $notifications = array();

    /**
     *
     * @return array
     */
    public static function getTypes()
    {
        return array(
            self::TYPE_ERROR,
            self::TYPE_SUCCESS,
        );
    }

    /**
     *
     * @param \Swift_Mailer $mailer
     */
    public function __construct(\Swift_Mailer $mailer = null)
    {
        if ($mailer) {
            $this->setMailer($mailer);
        }
    }

    /**
     *
     * @param \Swift_Mailer $mailer
     */
    public function setMailer(\Swift_Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     *
     * @return \Swift_Mailer
     */
    protected function getMailer()
    {
        return $this->mailer;
    }

    /**
     *
     * @param   \Swift_Message  $message
     * @param   string          $type     alert|success
     * @throws  \Libcast\JobQueue\Exception\NotificationException
     */
    public function addNotification(\Swift_Message $message, $type)
    {
        if (!in_array($type, self::getTypes())) {
            throw new NotificationException("The type '$type' is not valid.");
        }

        $this->notifications[$type] = $message;
    }

    /**
     *
     * @param   mixed   $type   alert|success|null
     * @return  mixed           array|\Swift_Message
     * @throws  \Libcast\JobQueue\Exception\NotificationException
     */
    public function getNotification($type = null)
    {
        if (in_array($type, self::getTypes())) {
            if (!isset($this->notifications[$type])) {
                throw new NotificationException("There is not '$type' notification.");
            }

            return $this->notifications[$type];
        }

        return $this->notifications;
    }

    /**
     *
     * @return  \Swift_Message
     * @throws  \Libcast\JobQueue\Exception\NotificationException
     */
    public function getSuccessNotification()
    {
        return $this->getNotification(self::TYPE_SUCCESS);
    }

    /**
     *
     * @return  \Swift_Message
     * @throws  \Libcast\JobQueue\Exception\NotificationException
     */
    public function getErrorNotification()
    {
        return $this->getNotification(self::TYPE_ERROR);
    }

    /**
     *
     * @param string $type alert|success
     * @throws \Libcast\JobQueue\Exception\NotificationException
     */
    public function sendNotification($type)
    {
        if (!isset($this->notifications[$type])) {
            throw new NotificationException("There is not '$type' notification.");
        }

        if (!$this->mailer) {
            throw new NotificationException('A mailer must be set.');
        }

        $this->getMailer()->send($this->notifications[$type]);
    }

    /**
     *
     * @see http://www.php.net/manual/en/serializable.serialize.php
     */
    public function serialize()
    {
        $data = array();

        foreach ($this->notifications as $type => $message) {
            /* @var $message \Swift_Message */
            $data[$type] = array(
                'subject'       => $message->getSubject(),
                'sender'        => $message->getSender(),
                'from'          => $message->getFrom(),
                'to'            => $message->getTo(),
                'body'          => $message->getBody(),
                'format'        => $message->getFormat(),
                'content-type'  => $message->getContentType(),
                'charset'       => $message->getCharset(),
            );
        }

        return serialize($data);
    }

    /**
     *
     * @see http://www.php.net/manual/en/serializable.unserialize.php
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized);

        foreach ($data as $type => $message_data) {
            $message = \Swift_Message::newInstance()->
                    setSubject($message_data['subject'])->
                    setSender($message_data['sender'])->
                    setFrom($message_data['from'])->
                    setTo($message_data['to'])->
                    setBody($message_data['body'])->
                    setFormat($message_data['format'])->
                    setContentType($message_data['content-type'])->
                    setCharset($message_data['charset']);

            $this->addNotification($message, $type);
        }
    }
}