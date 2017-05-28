#!/bin/sh
# /var/www/MyScripts/PWM/pwm_ssr_dimmer
# ln -s /var/www/MyScripts/PWM/pwm_FLANE.sh /usr/bin/pwm

case "$1" in
	start)
		pkill pwm_ssr_dimmer.php
		nohup /var/www/MyScripts/PWM/pwm_ssr_dimmer.php > /dev/null 2>&1 &
		# echo "Start PWM ssr dimmer..."
		;;
	stop)
		echo "Stop PWM ssr dimmer..."
		pkill pwm_ssr_dimmer.php
		nohup /var/www/MyScripts/PWM/pwm_stop.php > /dev/null 2>&1 &
		;;
	*)
		echo "Usage: /var/www/MyScripts/PWM/pwm_ssr_dimmer (start|stop)"
		exit 1
		;;
esac

exit 0
