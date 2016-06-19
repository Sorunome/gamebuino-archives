BOARD = gamebuino:avr:gamebuino
ARDUINO_DIR = /usr/share/arduino
ROOT_DIR = ../../../../../../../../
NAME = MYPROG
INO_FILE = file.ino

TEMPDIR := $(shell mktemp -d -u)
make:
	mkdir $(TEMPDIR)
	$(ARDUINO_DIR)/arduino-builder \
		-compile \
		-fqbn=$(BOARD) \
		-logger=machine \
		-hardware "$(ARDUINO_DIR)/hardware" \
		-hardware "/build/.arduino15/packages" \
		-hardware "/build/Arduino/hardware" \
		-tools "$(ARDUINO_DIR)/tools" \
		-tools "$(ARDUINO_DIR)/hardware/tools/avr" \
		-tools "/build/.arduino15/packages" \
		-built-in-libraries "$(ARDUINO_DIR)/libraries" \
		-libraries "/build/Arduino/libraries" \
		-ide-version=10609 \
		-build-path "$(TEMPDIR)" \
		-warnings=none \
		-prefs='build.warn_data_percentage=75' \
		-prefs='recipe.preproc.macros="{compiler.path}{compiler.cpp.cmd}" {compiler.cpp.flags} {preproc.macros.flags} -mmcu={build.mcu} -DF_CPU={build.f_cpu} -DARDUINO={runtime.ide.version} -DARDUINO_{build.board} -DARDUINO_ARCH_{build.arch} {compiler.cpp.extra_flags} {build.extra_flags} {includes} "{source_file}" -o "{preprocessed_file_path}"' \
		-prefs='compiler.c.cmd=$(ROOT_DIR)/usr/bin/avr-gcc' \
		-prefs='compiler.c.flags=-c -g -Os {compiler.warning_flags} -std=gnu11 -ffunction-sections -fdata-sections -MMD "-I$(ARDUINO_DIR)/hardware/tools/avr/avr/include"' \
		-prefs='compiler.cpp.cmd=$(ROOT_DIR)/usr/bin/avr-g++' \
		-prefs='compiler.cpp.flags=-c -g -Os {compiler.warning_flags} -std=gnu++11 -fno-exceptions -ffunction-sections -fdata-sections -fno-threadsafe-statics -MMD "-I$(ARDUINO_DIR)/hardware/tools/avr/avr/include"' \
		-verbose \
		"$(INO_FILE)"
	cp "$(TEMPDIR)/$(INO_FILE).hex" "/build/bin/$(NAME).HEX"
	cp "$(TEMPDIR)/$(INO_FILE).elf" "/build/bin/$(NAME).elf"
	
