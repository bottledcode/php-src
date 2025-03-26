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

static zend_class_entry *create_namespace(zend_string *interned_name) {
	zend_class_entry *ns = malloc(sizeof(zend_class_entry));
	zend_initialize_class_data(ns, 1);
	ns->type = ZEND_NAMESPACE_CLASS;
	ns->ce_flags |= ZEND_ACC_UNINSTANTIABLE;

	ns->name = zend_string_copy(interned_name);

	return ns;
}

static zend_class_entry *insert_namespace(const zend_string *name) {
	zend_class_entry *ns = NULL;
	zend_class_entry *parent_ns = global_namespace;
	const char *start = ZSTR_VAL(name);
	const char *end = start + ZSTR_LEN(name);
	const char *pos = start;
	size_t len = 0;

	while (pos <= end) {
		if (pos == end || *pos == '\\') {
			len = pos - start;
			zend_string *needle = zend_string_init(ZSTR_VAL(name), len, 0);

			ns = zend_hash_find_ptr(&namespaces, needle);

			if (!ns) {
				zend_string *interned_name = zend_new_interned_string(needle);
				ns = create_namespace(interned_name);
				ns->parent = parent_ns;
				zend_hash_add_ptr(&namespaces, interned_name, ns);
			}
			zend_string_release(needle);

			parent_ns = ns;
		}
		pos++;
	}

	return ns;
}

zend_class_entry *zend_resolve_namespace(zend_string *name) {
	if (global_namespace == NULL) {
		global_namespace = create_namespace(zend_string_copy(zend_empty_string));
		global_namespace->lexical_scope = NULL;
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

	zend_hash_destroy(&namespaces);
	global_namespace = NULL;
}
