#!/usr/bin/php
<?php
// Script per fermare il PWM e per azzerare i valori nel file temporaneo in /dev/shm/boilerXX.txt

// **********************
//VARIABILI DA MODIFICARE
// **********************

$pwm_ID=1; 				// Porta GPIO utilizzata per comando PWM (numerazione pin Wiringpi 1= GPIO 18)
$metnum = 6; 			// Meter ID Boiler

// *******************************************
// DA QUI IN POI NON SONO NECESSARIE MODIFICHE
// *******************************************

// LETTURA ULTIMO VALORE DA FILE CSV
$whpwmtot = 0;
if (empty($whpwmtot)) {
  $dir    = '/var/www/metern/data/csv/';
  $output = array();
  $output = glob($dir . '*.csv');
  sort($output);
  $cnt = count($output);

  if ($cnt > 0) {
      $lines      = file($output[$cnt - 1]);
      $contalines = count($lines);
      $whpwmtot = null;
      $j    = 0;
      while (!isset($whpwmtot)) {
          $j++;
          $array = preg_split('/,/', $lines[$contalines - $j]);
          $whpwmtot  = trim($array[$metnum]);
          if ($whpwmtot == '') {
              $whpwmtot = null;
          }
          if ($j == $contalines) {
              $whpwmtot = 0; // No previous value found, let's start from 0
          }
      }
  }
}

// CHIUSURA SCRIPT 
	system ("gpio pwm ".$pwm_ID." 0");
	$delta=0;
	$duty_per=0;
	$str = utf8_decode("$metnum($delta*W)\n$metnum($whpwmtot*Wh)\n${metnum}_1($duty_per*%)\n");
	file_put_contents("/dev/shm/boiler$metnum.txt", $str);
	
exit;
 ?>