<?php

namespace Controller;


class ControllerCheckemail extends Controller {

    public $tempDir = SERVER_ROOT . "Temp/";
    private static $countFalse = 0 ;
    public function process($action)
    {

        // Вывод сообщений
        $this->setOutput(new \Controller\OutputJson());
        switch ($action) {

            // Обработка файлов
            case 'batch':
                if($_GET['file']){
                    $this->checkFile($_GET['file']);
                }else {
                    $this->noRequestData();
                }
                break;

                // Проверка одного адреса из GET
            case 'single':
                if($_GET['email']){
                    $data['check']['email'] = $_GET['email'];
                    $rs = $this->filterEmail($data);
                    echo $rs[0]['fake']? 'invalid' : 'valid';
                }else {
                    $this->noRequestData();
                }
                break;

        }
    }

   // Проверка файла
    private function checkFile($fileName)
    {
        $fileIn = $this->tempDir . $fileName;

        // Проверяем существование файла и открываем его
        if ( file_exists($fileIn) && ($fp = fopen($fileIn, "rb"))!==false ) {
            // Смотрим расширение файла
            switch (pathinfo($fileIn)["extension"]){
                case "csv":
                    // формируем массив из файла с определением колонки с почтой
                    $i = 0;
                    while (($data = fgetcsv($fp, 0, ";")) !== FALSE)
                    {
                        foreach ($data as $field)
                        {
                            preg_match("/^([a-zA-Z0-9])+([\.a-zA-Z0-9_-])*@([a-zA-Z0-9_-])+(\.[a-zA-Z0-9_-]+)*\.([a-zA-Z]{2,6})$/", $field) ?
                                $readFromFile[$i]['email'] = $field :
                                $readFromFile[$i][] = $field;
                        }
                        $readFromFile[$i]['fake'] = 0;
                        $i++;
                    }
                    // Фильтруем почту фильтром))
                    $filteredEmails = $this->filterEmail($readFromFile);
                    // Формируем csv, и записываем в файл
                    $csv = "";
                    foreach($filteredEmails as  $v) {
                        $csv .= implode(";", $v). "\n";
                    }
                    $fileOut = "Filtered". date("dm-hi") . $fileName;
                    file_put_contents($this->tempDir . $fileOut, $csv);
                    break;

                case "txt":
                    break;
            }
            fclose($fp);
            return $this->outResult(true, 'Filtered: ' . self::$countFalse . " Output file: " . $fileOut   , false);
        }
        else
        {
            $this->outResult(false, 'Файл ' . $fileName . " не существует"  );
        }
    }

    // Метод фильтрации, новые фильтры добавлять сюды
    private function filterEmail($emails)
    {   $i = 0;
        foreach($emails as $ek => $ev) {

            $ev['email'] = mb_convert_case($ev['email'], MB_CASE_LOWER, "UTF-8");

            // Маслову не нравятся логины вида  t_71231231231, убираем их
            if(preg_match("/^t_([0-9]{7,10})/", $ev['email'])) {
                $i++;
                $ev['fake'] = 1;
            }

            // Убираем почты с кучей собак в адресе
            $domainEx = explode("@", $ev['email']);
            if(count($domainEx) > 2) {
                $i++;
                $ev['fake'] = 1;
            }

            // Проверка почты по RFC и проверка существования MX-записи в домене
            if(!$this->validateRFC($ev['email'])) {
                $i++;
                $ev['fake'] = 1;
            }

            // Убираем почты с большим количеством поддоменов
            $domainEx = explode("@", $ev['email']);
            $domainName = $domainEx[1];
            $domEx = explode(".", $domainName);
            if(count($domEx) > 2) {
                $i++;
                $ev['fake'] = 1;
            }

            $result[] = $ev;
        }
        self::$countFalse = $i;
        return $result;
    }

    // Проверка почты по RFC и домена на существование МХ записи
    private function validateRFC($email)
    {
        $literal = preg_match("/^([a-zA-Z0-9])+([\.a-zA-Z0-9_-])*@([a-zA-Z0-9_-])+(\.[a-zA-Z0-9_-]+)*\.([a-zA-Z]{2,6})$/", $email);
        $domain = explode('@', $email);
        $domainCheck = checkdnsrr($domain[1], 'MX');
        return $literal&&$domainCheck;
    }
}