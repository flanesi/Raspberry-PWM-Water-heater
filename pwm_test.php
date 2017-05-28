#!/usr/bin/php
<?php
/*
 * pwm_test: Raspberry PWM tests for obtaining the effective power of the boiler heating element
 * Rev. 1.0
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
 
// Script di test per determinare l'effettiva potenza della resistenza boiler
// da inserire poi nello script per la regolazione PWM

// per il test eseguire da terminale: php pwm_test.php

// **********************
//VARIABILI DA MODIFICARE
// **********************

$pwm_ID=1; 				// Porta GPIO utilizzata per comando PWM (numerazione pin Wiringpi 1= GPIO 18)
$pwm_range=1600;		// Range PWM : vedasi guida
$pwm_clock=2400;		// Clock PWM:  vedasi guida

// comando per la lettura  dei consumi
$lett_cons='cat /run/shm/metern2.txt | egrep "^2\(" | grep "*W)"';


//configura la porta GPIO per il PWM

system ("gpio mode ".$pwm_ID." PWM"); 			# imposta la porta GPIO in HW PWM Mode
system ("gpio pwm-ms");							# imposta il modo PWM Mark:Space
system ("gpio pwmr ".$pwm_range);				# imposta il range PWM
system ("gpio clock ".$pwm_ID." ".$pwm_clock);	# imposta il clock PWM
system ("gpio pwm ".$pwm_ID." 0");				# imposta a 0 la porta GPIO PWM (resistenza OFF)

// INIZIO TEST

echo "Start TEST .... attendere....\n\n";
for ($i=1 ; $i <= 3 ; $i++)
{
	system ("gpio pwm ".$pwm_ID." 0");
	sleep(10);
	$consumo0 = shell_exec($lett_cons);
	$consumo0 = substr($consumo0,2,-4);
	echo "Consumo OFF $i : \t$consumo0 W\n";
	system ("gpio pwm ".$pwm_ID." ".$pwm_range);
	sleep(15);
	$consumo1 = shell_exec($lett_cons);
	$consumo1 = substr($consumo1,2,-4);
	echo "Consumo ON  $i : \t$consumo1 W\n";
	$pot[$i]=$consumo1-$consumo0;		
	echo "Pot. Resistenza $i: \t$pot[$i] W\n\n";
}

$media = round(array_sum($pot)/count($pot),0);
echo "POT. RESISTENZA MEDIA \t$media W\n";	
	
// FINE TEST
system ("gpio pwm ".$pwm_ID." 0");
exit;
 ?>

