<?php

return [
    // Rows of /style list and /<panel> list that can be clicked (most lists are far shorter).
    'clickable_rows' => 60,

    // Per panel kind: how many templates /<panel> list shows at most.
    'list_max' => ['admin' => 40, 'donate' => 20, 'records' => 50, 'vote' => 10],

    'messages' => [
        'style_none'       => '{#server}> {#error}No style specified, use {#highlite}$i /style help {#error}!',
        'style_invalid'    => '{#server}> {#error}No valid style file, use {#highlite}$i /style list {#error}!',
        'style_off'        => '{#server}> TMF window style disabled!',
        'admin_missing'    => '{#server}> {#error}Unknown admin command or missing parameter(s): {#highlite}$i {1}',
        'style_default'    => '{#server}> Style reset to server default {#highlite}{1}{#server} !',
        'style_selected'   => '{#server}> Style {#highlite}{1}{#server} selected!',
        'admin_style_reset' => '{#server}>> {#admin}{1}$z$s {#highlite}{2}$z$s{#admin} reset default window style',
        'admin_style_set'   => '{#server}>> {#admin}{1}$z$s {#highlite}{2}$z$s{#admin} selects default window style {#highlite}{3}',
        'theme_list'        => '{#server}> Themes: {#highlite}{1}{#server} (now {#highlite}{2}{#server}). Use {#highlite}/theme name',
        'theme_invalid'     => '{#server}> {#error}No such theme, choose one of: {#highlite}{1}',
        'theme_selected'    => '{#server}> Theme {#highlite}{1}{#server} selected!',
        'theme_default'     => '{#server}> Theme reset to server default {#highlite}{1}{#server} !',
        'admin_theme_set'   => '{#server}>> {#admin}{1}$z$s {#highlite}{2}$z$s{#admin} selects default theme {#highlite}{3}',
        'panel_none'       => '{#server}> {#error}No {1} panel specified, use {#highlite}$i {2} help {#error}!',
        'panel_invalid'    => '{#server}> {#error}No valid {1} panel file, use {#highlite}$i {2} list {#error}!',
        'panel_off'        => '{#server}> {1} panel disabled!',
        'panel_default'    => '{#server}> {1} panel reset to server default {#highlite}{2}{#server} !',
        'panel_selected'   => '{#server}> {1} panel {#highlite}{2}{#server} selected!',
        'default_reset'    => '{#server}>> {#admin}{1}$z$s {#highlite}{2}$z$s{#admin} reset default {3} panel',
        'default_set'      => '{#server}>> {#admin}{1}$z$s {#highlite}{2}$z$s{#admin} selects default {3} panel {#highlite}{4}',
        'default_reset_self' => '{#server}> {#admin}{1}$z$s {#highlite}{2}$z$s{#admin} reset default {3} panel',
        'default_set_self'   => '{#server}> {#admin}{1}$z$s {#highlite}{2}$z$s{#admin} selects default {3} panel {#highlite}{4}',
    ],
];
