/*
+----------------------------------------------------------------------+
   | Zend Engine                                                          |
   +----------------------------------------------------------------------+
   | Copyright (c) Zend Technologies Ltd. (http://www.zend.com)           |
   +----------------------------------------------------------------------+
   | This source file is subject to version 2.00 of the Zend license,     |
   | that is bundled with this package in the file LICENSE, and is        |
   | available through the world-wide-web at the following url:           |
   | http://www.zend.com/license/2_00.txt.                                |
   | If you did not receive a copy of the Zend license and are unable to  |
   | obtain it through the world-wide-web, please send a note to          |
   | license@zend.com so we can mail you a copy immediately.              |
   +----------------------------------------------------------------------+
   | Authors: Rob Landers <rob@bottled.codes>                             |
   |                                                                      |
   +----------------------------------------------------------------------+
*/

#include "zend_namespaces.h"

#include <zend_smart_str.h>

#include "zend_API.h"
#include "zend_hash.h"

static zend_class_entry *global_namespace = NULL;
static HashTable namespaces;

static zend_class_entry *create_namespace(zend_string *name) {
	zend_class_entry *ns = pemalloc(sizeof(zend_class_entry), 1);
	memset(ns, 0, sizeof(zend_class_entry));
	zend_initialize_class_data(ns, 1);
	ns->type = ZEND_NAMESPACE_CLASS;

	zend_string *interned_name = zend_new_interned_string(zend_string_copy(name));
	ns->name = interned_name;

	return ns;
}

static zend_class_entry *insert_namespace(const zend_string *name) {
	zend_class_entry *ns = NULL;
	zend_class_entry *parent_ns = global_namespace;
	zend_string *part = NULL;
	const char *start = ZSTR_VAL(name);
	const char *end = start + ZSTR_LEN(name);
	const char *pos = start;
	size_t len = 0;

	smart_str current_ns = {0};

	while (pos <= end) {
		if (pos == end || *pos == '\\') {
			len = pos - start;
			part = zend_string_init(start, len, 0);

			if (current_ns.s) {
				smart_str_appendc(&current_ns, '\\');
			}
			smart_str_appendl(&current_ns, ZSTR_VAL(part), ZSTR_LEN(part));
			smart_str_0(&current_ns);

			zend_string *needle = zend_string_init(ZSTR_VAL(current_ns.s), ZSTR_LEN(current_ns.s), 0);
			ns = zend_hash_find_ptr(&namespaces, needle);

			if (!ns) {
				ns = create_namespace(needle);
				ns->parent = parent_ns;
				zend_hash_add_ptr(&namespaces, current_ns.s, ns);
			}

			zend_string_release(part);
			zend_string_release(needle);

			parent_ns = ns;
			start = pos + 1;
		}
		pos++;
	}

	smart_str_free(&current_ns);

	return ns;
}

zend_class_entry *zend_resolve_namespace(zend_string *name) {
	if (global_namespace == NULL) {
		global_namespace = create_namespace(zend_empty_string);
		zend_hash_init(&namespaces, 8, NULL, ZEND_CLASS_DTOR, 1);
		zend_hash_add_ptr(&namespaces, zend_empty_string, global_namespace);
	}

	if (name == NULL || ZSTR_LEN(name) == 0) {
		return global_namespace;
	}

	zend_string *lc_name = zend_string_tolower(name);
	zend_class_entry *ns = zend_hash_find_ptr(&namespaces, lc_name);

	if (!ns) {
		ns = insert_namespace(lc_name);
	}

	zend_string_release(lc_name);

	return ns;
}

zend_class_entry *zend_lookup_namespace(zend_string *name) {
	zend_string *lc_name = zend_string_tolower(name);
	zend_class_entry *ns = zend_hash_find_ptr(&namespaces, lc_name);
	zend_string_release(lc_name);

	return ns;
}

void zend_destroy_namespaces(void) {
	if (global_namespace == NULL) {
		return;
	}

	zend_class_entry *ns = NULL;
	ZEND_HASH_FOREACH_PTR(&namespaces, ns) {
		zend_string_release(ns->name);
	} ZEND_HASH_FOREACH_END();

	zend_hash_destroy(&namespaces);
	zend_string_release(global_namespace->name);
	pefree(global_namespace, 1);
}
