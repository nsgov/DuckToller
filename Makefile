all: lib twitter
.PHONEY: clean recursive

lib: recursive
	$(MAKE) -C $@

twitter: recursive
	$(MAKE) -C $@

clean:
	@for d in */; do \
		[ -f $$d/Makefile ] && echo $$d && $(MAKE) -C $$d $@; \
	done

recursive:
