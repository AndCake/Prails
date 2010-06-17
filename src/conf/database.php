<?php

$arr_database = Array
(
	// language tables		--- should be made optional
	"language"=>Array(
		"name"=>"VARCHAR(255)",
		"abbreviation"=>"VARCHAR(255)",
	),
	"texts"=>Array(
		"fk_language_id"=>"INT(11) NOT NULL",
		"identifier"=>"VARCHAR(255)",
		"content"=>"TEXT",
	),
	
	// builder tables
	"module"=>Array(
		"fk_module_id"=>"INT(11) NOT NULL",		// link to parent module
	    "fk_user_id"=>"INT(11) NOT NULL",
	    "name"=>"VARCHAR(255)",
		"js_code"=>"TEXT",
	    "style_code"=>"TEXT",
	),
	"library"=>Array(
		"fk_module_id"=>"INT(11) NOT NULL",
		"fk_user_id"=>"INT(11) NOT NULL",
		"name"=>"VARCHAR(255)",
		"code"=>"TEXT",
	),
	"handler"=>Array(
	    "fk_module_id"=>"INT(11) NOT NULL",
	    "event"=>"VARCHAR(255)",
		"flag_ajax"=>"INT(1) NOT NULL",
	    "code"=>"TEXT",
	    "html_code"=>"TEXT",
	),
	"configuration"=>Array(
		"fk_module_id" => "INT(11) NOT NULL",			// zero for global configuration
		"flag_public" => "INT(1) NOT NULL",			// 0 = private, 1 = public
		"name" => "VARCHAR(255)",
		"value" => "VARCHAR(255)",
	),
	"data"=>Array(
	    "fk_module_id"=>"INT(11) NOT NULL",
	    "name"=>"VARCHAR(255)",
	    "code"=>"TEXT",
	),
	"tag"=>Array(
		"fk_user_id"=>"INT(11) NOT NULL",
		"name"=>"VARCHAR(255)",
		"html_code"=>"TEXT",
	),
	"resource"=>Array(
		"fk_module_id"=>"INT(11) NOT NULL",
		"name"=>"VARCHAR(255)",
		"type"=>"VARCHAR(255)",
		"data"=>"LONGBLOB",
	),
	"table"=>Array(
		"fk_user_id"=>"INT(11) NOT NULL",
		"name"=>"VARCHAR(255)",
		"field_names"=>"VARCHAR(1024)",
		"field_types"=>"VARCHAR(1024)",
	),
	
	// history tables
	"module_history"=>Array(
		"fk_original_id"=>"INT(11) NOT NULL",		// original module id
		"fk_module_id"=>"INT(11) NOT NULL",
		"name"=>"VARCHAR(255)",
		"style_code"=>"LONGTEXT",					// should store only the difference between previous and new state
		"js_code"=>"LONGTEXT",						// should store only the difference between previous and new state
		"change_time"=>"INT(20)",
	),
	"library_history"=>Array(
		"fk_original_id"=>"INT(11) NOT NULL",		// original module id
		"fk_module_id"=>"INT(11) NOT NULL",
		"fk_user_id"=>"INT(11) NOT NULL",
		"name"=>"VARCHAR(255)",
		"code"=>"LONGTEXT",					// should store only the difference between previous and new state
		"change_time"=>"INT(20)",
	),
	"handler_history"=>Array(
		"fk_original_id"=>"INT(11) NOT NULL",
		"fk_module_id"=>"INT(11) NOT NULL",
		"event"=>"VARCHAR(255)",
		"code"=>"TEXT",
		"html_code"=>"TEXT",
		"change_time"=>"INT(20)",
	),
	"configuration_history"=>Array(
		"fk_original_id"=>"INT(11) NOT NULL",
		"fk_module_id"=>"INT(11) NOT NULL",
		"flag_public"=>"INT(1) NOT NULL",
		"name"=>"VARCHAR(255)",
		"value"=>"VARCHAR(255)",
		"change_time"=>"INT(20)",
	),
	"data_history"=>Array(
		"fk_original_id"=>"INT(11) NOT NULL",
		"fk_module_id"=>"INT(11) NOT NULL",
		"name"=>"VARCHAR(255)",
		"code"=>"TEXT",
		"change_time"=>"INT(20)",
	),	
	"tag_history"=>Array(
		"fk_original_id"=>"INT(11) NOT NULL",
		"fk_user_id"=>"INT(11) NOT NULL",
		"name"=>"VARCHAR(255)",
		"html_code"=>"TEXT",
		"change_time"=>"INT(20)",
	),	
	"table_history"=>Array(
		"fk_original_id"=>"INT(11) NOT NULL",
		"fk_user_id"=>"INT(11) NOT NULL",
		"name"=>"VARCHAR(255)",
		"field_names"=>"VARCHAR(1024)",
		"field_types"=>"VARCHAR(1024)",
	)
);

?>
