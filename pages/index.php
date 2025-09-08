<?php

$package = rex_addon::get('push_it');
echo rex_view::title(rex_i18n::msg('pushit_title'));
rex_be_controller::includeCurrentPageSubPath();
