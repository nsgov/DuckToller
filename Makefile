all: lib twitter
.PHONEY: clean recursive

lib twitter cache: recursive
	$(MAKE) -C $@

clean:
	@for d in */; do \
		[ -f $$d/Makefile ] && echo $$d && $(MAKE) -C $$d $@; \
	done

recursive:
