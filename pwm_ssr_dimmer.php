#!/usr/bin/php
<?php

/*
 * pwm_ssr_dimmer: Raspberry PWM dimmer for optimize the PV self-consumption, with boiler temperature control
 * Rev. 1.22
 *
 * Copyright (C) 2016 Flavio Anesi <www.flanesi.it>
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

// Script per leggere i dati di potenza istantanea prodotta e consumata 
// ed attivare un carico resistivo (resistenza boiler) con modulazione in PWM
// ad intervalli minimi di 1/STEP del carico (vedi tabelle sotto),
// mediante relè allo stato solido zero crossing FOTEK SSR-25DA
// Lo script crea anche un meter virtuale per la misura dell'energia inviata al boiler
// ed uno per la % di regolazione della resistenza boiler
// **********************
// Usare cron per impostare l'avvio automatico al mattino inseredendo in /etc/crontab la riga
// 45 6 * * * root /var/www/MyScripts/PWM/pwm_ssr_dimmer.sh start
// lo script si ferma automaticamente dopo 20 cicli (dopo le ore 16.00) con produzione = 0 (cioè alla sera in assenza di sole)

// ********************** 
//VARIABILI DA MODIFICARE
// **********************
$mettemp = 0;			// MeterID Temperatura Boiler (0 se non si utilizza il sensore di temperatura)
$maxtemp = 70;			// Temperatura massima Boiler
$metnum = 6; 			// MeterID Boiler
$frequenza=6;			// intervallo in secondi fra le letture(minimo 6 sec)
$minimo=10; 			// potenza in W da non utilizzare - se impostato negativo preleva dalla rete
						// serve a tenere conto delle tolleranze del variatore di potenza, della resistenza e del contatore.
$pwm_ID=1; 				// Porta GPIO utilizzata per comando PWM (numerazione pin Wiringpi 1= GPIO 18)
$tipo_reg=1;			// Tipo di regolazione: 1 a treni di SEMIONDE / 2 a treni di SINUSOIDI COMPLETE (consigliato 1)
$pwm_range=1600;		// Range PWM : valore consigliato 1600
$pwm_clock=2400;		// Clock PWM:  valore consigliato 2400
						// 		Treni di SEMIONDE					  Treni di SINUSOIDI COMPLETE
						// ______________________________			______________________________
						//	range	|	clock	| step	|			|	range	|	clock	| step	|
						//	4000	|	2400	|	50	|			|	4000	|	3840	|	40	|
						//	2000	|	2400	|	25	|			|	4000	|	2400	|	25	|
						//	1600	|	2400	|	20	|			|	3200	|	2400	|	20	|
						//	800		|	2400	|	10	|			|	1600	|	2400	|	10	|
						//	400		|	2400	|	5	|			|	800		|	2400	|	5	|
						//	160		|	2400	|	2	|			|	320		|	2400	|	2	|
						// ______________________________			______________________________

$resistenza=1200;		// valore in Watt della resistenza da dimmerare (DEVE ESSERE IL PIU' PRECISO POSSIBILE)
						// NOTA: la potenza in W erogata dalla resistenza è proporzionale alla tensione applicata
						// pertanto è necessario determinare tale valore in un momento in cui l'inverter stà 
						// producendo e la tensione è ad un valore medio delle normali oscillazioni della giornata

			
// comando per la lettura  dei consumi
$lett_cons="poolerconsumi 2 power";
// comando per la lettura della produzione
$lett_prod="pool123s power";
// comando per la lettura del consumo del boiler (restituisce solo il numero corrispondente ai Wh)
$lett_pwm="cat /dev/shm/boiler$metnum.txt | egrep \"^$metnum\(\" | grep \"*Wh)\" | cut -d \"(\" -f2 | cut -d \"*\" -f1";
// comando per la lettura della temperatura del boiler
$lett_temp="cat /dev/shm/metern$mettemp.txt | egrep \"^$mettemp\(\" | grep \"*C)\" | cut -d \"(\" -f2 | cut -d \"*\" -f1";

// *******************************************
// DA QUI IN POI NON SONO NECESSARIE MODIFICHE
// *******************************************
$controllo=0;								// variabile di controllo x fine giornata
$delta=0;									// delta iniziale
$duty=0;									// valore iniziale da assegnare al duty cycle PWM
$pwm_freq=19200000/($pwm_range*$pwm_clock); // frequenza PWM (Hz)
$pwm_period=1/$pwm_freq;					// periodo PWM (s)
$pwm_pulse=$pwm_period/$pwm_range;			// singolo impulso PWM (s)
$pwm_duty_step=(0.01*$tipo_reg)/$pwm_pulse;	// minimo step duty cycle PWM
$pwm_step=(100/$tipo_reg)/$pwm_freq;		// N. di step (intervalli di regolazione possibili)
$step=$resistenza/$pwm_step;				// step minimo del carico regolabile (W)

//	DECOMMENTARE PER DEBUG
/*
echo "Frequenza PWM: \t$pwm_freq Hz\n";
echo "Periodo PWM : \t$pwm_period s\n";
echo "Impulso PWM : \t$pwm_pulse s\n";
echo "Duty step : \t$pwm_duty_step \n";
echo "N. Step : \t$pwm_step \n";
echo "Reg. minima : \t$step W\n";
*/

//configura la porta GPIO per il PWM

system ("gpio mode ".$pwm_ID." PWM"); 			# imposta la porta GPIO in HW PWM Mode
system ("gpio pwm-ms");							# imposta il modo PWM Mark:Space
system ("gpio pwmr ".$pwm_range);				# imposta il range PWM
system ("gpio clock ".$pwm_ID." ".$pwm_clock);	# imposta il clock PWM
system ("gpio pwm ".$pwm_ID." 0");				# imposta a 0 la porta GPIO PWM (resistenza OFF)

// LETTURA VALORE DA FILE CSV - ultimo valore del contatore 
$whpwmtot = shell_exec($lett_pwm);
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

// INIZIO CICLO DI CONTROLLO
$start = microtime(true);
while ($controllo < 20){
	
	$TODAY = date("d.m.Y-H:i:s");	// Data nel formato DD.MM.YYYY-HH:min:sec
	$ORA = date("H");
	
	// lettura potenza consumata istantanea ed estrazione del solo valore numerico
	$consumo = shell_exec($lett_cons);
	$consumo = substr($consumo,2,-4);
	// lettura potenza generata istantanea ed estrazione del solo valore numerico
	$generata = shell_exec($lett_prod);
	$generata = substr($generata,2,-4);
	// lettura temperatura boiler
	if (file_exists("/dev/shm/metern$mettemp.txt")) {
		$temp = shell_exec($lett_temp);
		$temp = substr($temp, 0, 2);
                if (is_numeric($temp)) {
			$temp = $temp;
		}else{
			$temp = 0;
		}
	}else{
	        $temp = 0;
        }
	if ($generata==0 and $ORA > 16){$controllo++;}
	$bilancio=$generata-$consumo-$minimo+$delta;
	
//	DECOMMENTARE PER DEBUG
/*
		echo "________________________________________\n\r";
		echo "$TODAY\n";
		echo "$ORA\n";
		echo "$pathmettemp\n";
		echo "Temperatura Boiler : $temp °C\n";
		echo "Potenza generata  : \t$generata W\n";
		echo "Potenza consumata : \t$consumo W\n";
		echo "Potenza minima : \t$minimo W\n";
		echo "Delta resistenza : \t$delta W\n";
		echo "Bilancio : \t\t$bilancio W\n";
		echo "Controllo : \t\t$controllo\n";
		echo "________________________________________\n";
*/

	if ($bilancio >= $step and $consumo > 0 and $temp < $maxtemp){
			$duty=floor($bilancio/$step)*$pwm_duty_step;
			if ($duty >=$pwm_range) $duty=$pwm_range;
			//echo "PWM ON: Duty cycle= $duty \n";
			$delta=$duty/$pwm_duty_step*$step;
			//echo "Potenza resistenza: $delta W\n";
			$time_elapsed_secs = microtime(true) - $start;
			$whpwmintervallo=($delta*$time_elapsed_secs)/3600;

			if ($whpwmintervallo<0) {$whpwmintervallo=0;}
			$whpwmtot+=$whpwmintervallo;
			$start = microtime(true);
			//echo "Consumo intervallo: $whpwmintervallo Wh";
			//echo "Consumo accumulato: $whpwmtot Wh";
			system ("gpio pwm ".$pwm_ID." ".$duty);
			$whpwmtot_out= round($whpwmtot, 2);
			$duty_per=$duty/16;
            $str = utf8_decode("$metnum($delta*W)\n$metnum($whpwmtot_out*Wh)\n${metnum}_1($duty_per*%)\n");
			file_put_contents("/dev/shm/boiler$metnum.txt", $str);
			sleep($frequenza);
		} else {
			//echo "PWM OFF\n";
			system ("gpio pwm ".$pwm_ID." 0");
			$delta=0;
			$whpwmtot_out= round($whpwmtot, 2);
			$duty_per=0;
            $str = utf8_decode("$metnum($delta*W)\n$metnum($whpwmtot_out*Wh)\n${metnum}_1($duty_per*%)\n");
			file_put_contents("/dev/shm/boiler$metnum.txt", $str);
			sleep($frequenza);
			$start = microtime(true);
	}
}
// FINE GIORNATA - CHIUSURA SCRIPT 
//echo "Fine giornata: $TODAY\n";
system ("gpio pwm ".$pwm_ID." 0");
exit;
 ?>

