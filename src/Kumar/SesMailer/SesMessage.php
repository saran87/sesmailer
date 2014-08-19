<?php


namespace Kumar\SesMailer;


class SesMessage {


    const SOURCE  = 'Source';

    const TO_ADDRESS = 'ToAddresses';

    const BCC_ADDRESS = 'BccAddresses';

    const CC_ADDRESS = 'CcAddresses';

    const DESTINATION = 'Destination';

    const MESSAGE = 'Message';

    const SUBJECT = 'Subject';

    const BODY = 'Body';

    const DATA = 'Data';

    const TEXT = 'Text';

    const HTML = 'Html';

    /**
     * @var array message
     */
    private $message = [];


    public function getMessage()
    {
        return $this->message;
    }
    /**
     * @param        $email
     * @param string $name
     */
    public function from($email,$name=null){

        if(isset($name)){
            $toAddress =  $name . '<' . $email . '>';
        }else{
            $toAddress  = $email;
        }

        $this->setMessage(self::SOURCE,$toAddress);
    }

    /**
     * @param $emailArray
     *
     * @return $this
     */
    public function to($address, $name = null){

        if(is_array($address)){
            $this->setDestination(self::TO_ADDRESS,$address);
        }else{
            $this->addDestination(self::TO_ADDRESS, $name . '<' . $address . '>');
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getTo(){

       return $this->getDestination(self::TO_ADDRESS);
    }

    /**
     * @param $emailArray
     *
     * @return $this
     */
    public function cc($emailArray){

        $this->setDestination(self::CC_ADDRESS,$emailArray);
        return $this;
    }

    /**
     * @param string|array $emailArray
     *
     * @return $this
     */
    public function bcc($emailArray){

        $this->setDestination(self::BCC_ADDRESS,$emailArray);
        return $this;
    }

    /**
     * @param $subject
     *
     * @return $this
     */
    public function subject($subject){
        $data = $this->getData($subject);
        $this->addMessage(self::SUBJECT,$data);
        return $this;
    }

    /**
     * @param $message
     *
     * @return $this
     */
    public function text($message){
        $data[self::TEXT] = $this->getData($message);
        $this->setBody($data);

        return $this;
    }

    /**
     * @param $message
     *
     * @return $this
     */
    public function html($message){
        $data[self::HTML] = $this->getData($message);
        $this->setBody($data);

        return $this;
    }

    /**
     * @param array $data
     */
    private function setBody($data){
        $this->addMessage(self::BODY,$data);
    }

    /**
     * @param $key
     * @param $value
     */
    private function setMessage($key, $value){
        $this->message[$key] = $value;
    }

    /**
     * @param $string
     * @param $emailArray
     */
    private function setDestination($string, $emailArray)
    {
        if(!array_key_exists(self::DESTINATION,$this->message)){
            $this->message[self::DESTINATION] = [];
        }

        $this->message[self::DESTINATION][$string] = $emailArray;
    }

    /**
     * @param $string
     * @param $emailArray
     */
    private function addDestination($string, $emailArray)
    {
        if(!array_key_exists(self::DESTINATION,$this->message)){
            $this->message[self::DESTINATION] = [];
        }

        $this->message[self::DESTINATION][$string][] = $emailArray;
    }

    /**
     * @param $key
     *
     * @return array
     */
    private function getDestination($key)
    {
        if(array_key_exists(self::DESTINATION,$this->message)){
           return  array_key_exists($key, $this->message[self::DESTINATION]) ? $this->message[self::DESTINATION][$key] : [];
        }

        return [];
    }

    /**
     * @param $value
     *
     * @return array
     */
    private function getData($value)
    {
        return [
            Self::DATA => $value
        ];
    }

    /**
     * @param $string
     * @param $data
     */
    private function addMessage($string, $data)
    {
        if(!array_key_exists(self::MESSAGE, $this->message)){
            $this->message[self::MESSAGE] = [];
        }

        $this->message[self::MESSAGE][$string] = $data;
    }


} 