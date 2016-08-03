#!/bin/bash
# This script will run inside the template box to set everything up!

arduino_version="1.6.10"
builder_version="1.3.14"

id=`id -u`
if [ $id = "0" ]; then
	# make sure we have internet
	dhclient eth0

	# we want to be up-to-date!
	apt-get update
	apt-get -y dist-upgrade

	# install dependencies
	apt-get -y install rsync git wget inetutils-tools inetutils-ping unzip gcc-avr iptables build-essential sudo

	# add the user
	groupadd build -g 1000
	useradd build -d /build -m -s /bin/bash -u 1000 -g 1000

	# download and install the arduino library
	wget https://www.arduino.cc/download.php?f=/arduino-$arduino_version-linux64.tar.xz -O /arduino.tar.xz
	tar xpvf /arduino.tar.xz -C /usr/share
	mv /usr/share/arduino-$arduino_version /usr/share/arduino
	rm /arduino.tar.xz
	wget https://downloads.arduino.cc/tools/arduino-builder-linux64-$builder_version.tar.bz2 -O arduino-builder.tar.bz2
	tar jxvf arduino-builder.tar.bz2 -C /usr/share/arduino
	rm arduino-builder.tar.bz2
	
	mkdir /makefiles
	chmod 777 /makefiles
	
	touch /source.zip
	chmod 777 /source.zip
	
	mkdir /libraries
	wget https://github.com/Rodot/Gamebuino/archive/master.zip -O /tmp/gamebuino.zip
	unzip /tmp/gamebuino.zip -d /tmp
	rm /tmp/gamebuino.zip
	mv /tmp/Gamebuino-master /libraries/gb1
	mv /libraries/gb1/hardware/gamebuino/* /libraries/gb1/
	
	sudo -u build $0
else
	# let's do the setup for the unpriviliged user!
	mkdir -p /build/bin
	mkdir -p /build/.arduino15/packages/gamebuino/hardware/avr
	
	ln -s /libraries/gb1 /build/.arduino15/packages/gamebuino/hardware/avr/gb1
	
	mkdir -p /build/Arduino/hardware
	mkdir -p /build/Arduino/libraries
fi
