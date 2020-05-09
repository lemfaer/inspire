<?php

function partial($func, $args = array()) {
	return function () use ($func, $args) {
		$input = func_get_args();

		for ($i = 0; $i < ($args ? max(array_keys($args)) : 0); $i++) {
			if (!array_key_exists($i, $args)) {
				$args[$i] = array_shift($input);
			}
		}

		ksort($args);
		$args = array_merge($args, $input);

		return call_user_func_array($func, $args);
	};
}
