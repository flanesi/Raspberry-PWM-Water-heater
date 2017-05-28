# Raspberry-PWM-Water-heater
Raspberry PWM dimmer for optimize the PV self-consumption, with boiler temperature control

# PREMESSA
I requisiti essenziali per poter utilizzare questo sistema sono:
•	sistemi realizzati mediante l’immagine Solarjessie v2.6 e successive;
•	MeterN versione 0.8.3 e successive
•	Configurazione standard (primo meter libero n. 6)

Forum di discussione: http://www.flanesi.it/forum/viewtopic.php?f=20&t=1842

# DESCRIZIONE

Lo script serve per ottimizzare al masimo l'autoconsumo di un impianto fotovoltaico dirotando tutto l'eccesso di produzione verso un boiler per la produzione di acqua calda sanitaria.
Con lo stesso Raspberry su cui avete installato 123Solar e MeterN, lo utilizzeremo anche per fare una modulazione della resistenza del boiler mediante l'uscita PWM del Raspberry con un semplice relè SSR zero crossing.
In questo modo si riesce a fare una regolazione a treni di sinusoidi, regolando la potenza con 20 step (che, ad esempio, su 1200W di resistenza significa gradini incrementali da 60W).

# GUIDA

Vedasi il file Intructions_IT.doc presente nel repository

