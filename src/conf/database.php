<?php
/**
    Prails Web Framework
    Copyright (C) 2012  Robert Kunze

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

$arr_database = Array
(
	// language tables
	"language"=>Array(
		"name"=>"VARCHAR(255)",
		"abbreviation"=>"VARCHAR(255)",
		"isDefault" => "TINYINT NOT NULL",
	),
	"texts"=>Array(
		"fk_language_id"=>"INTEGER NOT NULL REFERENCES tbl_prailsbase_language",
		"identifier"=>"VARCHAR(255)",
		"content"=>"TEXT",
		"type" => "TINYINT NOT NULL",
		"decorator" => "VARCHAR(255)",
		"title" => "VARCHAR(255)",
		"description" => "TEXT",
		"custom" => "TEXT",
	),
	
	"custom" => Array(
		"type" => "VARCHAR(255)",
		"data" => "TEXT",
	),
	
	// builder tables
	"module"=>Array(
		"fk_module_id"=>"INTEGER NOT NULL",		// link to parent module
	    "fk_user_id"=>"BIGINT NOT NULL",
	    "name"=>"VARCHAR(255)",
		"header_info" => "TEXT",
		"js_code"=>"TEXT",
	    "style_code"=>"TEXT",
	),
	"library"=>Array(
		"fk_module_id"=>"INTEGER NOT NULL",
		"fk_user_id"=>"BIGINT NOT NULL",
		"fk_resource_id" => "INTEGER NOT NULL",
		"name"=>"VARCHAR(255)",
		"code"=>"TEXT",
	),
	"handler"=>Array(
	    "fk_module_id"=>"INTEGER NOT NULL REFERENCES tbl_prailsbase_module",
	    "event"=>"VARCHAR(255)",
	    "flag_ajax"=>"TINYINT NOT NULL",
	    "flag_cacheable"=>"TINYINT NOT NULL",
		"hook" => "VARCHAR(255)",
		"schedule" => "VARCHAR(255)",
	    "code"=>"TEXT",
	    "html_code"=>"TEXT",
	),
	"configuration"=>Array(
		"fk_module_id" => "INTEGER NOT NULL REFERENCES tbl_prailsbase_module",
		"flag_public" => "TINYINT NOT NULL",			// 0 = private, 1 = public
		"name" => "VARCHAR(255)",
		"value" => "VARCHAR(255)",
	),
	"data"=>Array(
	    "fk_module_id"=>"INTEGER NOT NULL REFERENCES tbl_prailsbase_module",
	    "name"=>"VARCHAR(255)",
	    "code"=>"TEXT",
	),
	"tag"=>Array(
		"fk_user_id"=>"BIGINT NOT NULL",
		"name"=>"VARCHAR(255)",
		"html_code"=>"TEXT",
	),
	"resource"=>Array(
		"fk_module_id"=>"INTEGER NOT NULL",
		"name"=>"VARCHAR(255)",
		"type"=>"VARCHAR(255)",
		"data"=>"LONGBLOB",
		"tree"=>"TEXT",
	),
	"table"=>Array(
		"fk_user_id"=>"BIGINT NOT NULL",
		"name"=>"VARCHAR(255)",
		"field_names"=>"VARCHAR(1024)",
		"field_types"=>"VARCHAR(1024)",
	),
	
	// testing tables
	"testcase" => Array(
		"fk_module_id" => "INTEGER NOT NULL REFERENCES tbl_prailsbase_module",
		"name" => "VARCHAR(255)",
		"setup" => "TEXT",
		"run" => "TEXT",
		"teardown" => "TEXT"
	),	
	
	// history tables
	"module_history"=>Array(
		"fk_original_id"=>"INTEGER NOT NULL REFERENCES tbl_prailsbase_module",		// original module id
		"fk_module_id"=>"INTEGER REFERENCES tbl_prailsbase_module",
		"name"=>"VARCHAR(255)",
		"header_info" => "TEXT",
		"style_code"=>"LONGTEXT",					// should store only the difference between previous and new state
		"js_code"=>"LONGTEXT",						// should store only the difference between previous and new state
		"change_time"=>"BIGINT",
	),
	"library_history"=>Array(
		"fk_original_id"=>"INTEGER NOT NULL REFERENCES tbl_prailsbase_library",		// original module id
		"fk_module_id"=>"INTEGER REFERENCES tbl_prailsbase_module",
		"fk_user_id"=>"BIGINT NOT NULL",
		"name"=>"VARCHAR(255)",
		"code"=>"LONGTEXT",					// should store only the difference between previous and new state
		"change_time"=>"BIGINT",
	),
	"handler_history"=>Array(
		"fk_original_id"=>"INTEGER NOT NULL REFERENCES tbl_prailsbase_handler",
		"fk_module_id"=>"INTEGER NOT NULL REFERENCES tbl_prailsbase_module",
		"event"=>"VARCHAR(255)",
		"code"=>"TEXT",
		"html_code"=>"TEXT",
		"change_time"=>"BIGINT",
	),
	"configuration_history"=>Array(
		"fk_original_id"=>"INTEGER NOT NULL REFERENCES tbl_prailsbase_configuration",
		"fk_module_id"=>"INTEGER NOT NULL REFERENCES tbl_prailsbase_module",
		"flag_public"=>"TINYINT NOT NULL",
		"name"=>"VARCHAR(255)",
		"value"=>"VARCHAR(255)",
		"change_time"=>"BIGINT",
	),
	"data_history"=>Array(
		"fk_original_id"=>"INTEGER NOT NULL REFERENCES tbl_prailsbase_data",
		"fk_module_id"=>"INTEGER NOT NULL REFERENCES tbl_prailsbase_module",
		"name"=>"VARCHAR(255)",
		"code"=>"TEXT",
		"change_time"=>"BIGINT",
	),	
	"tag_history"=>Array(
		"fk_original_id"=>"INTEGER NOT NULL REFERENCES tbl_prailsbase_tag",
		"fk_user_id"=>"BIGINT NOT NULL",
		"name"=>"VARCHAR(255)",
		"html_code"=>"TEXT",
		"change_time"=>"BIGINT",
	),	
	"table_history"=>Array(
		"fk_original_id"=>"INTEGER NOT NULL REFERENCES tbl_prailsbase_table",
		"fk_user_id"=>"BIGINT NOT NULL",
		"name"=>"VARCHAR(255)",
		"field_names"=>"VARCHAR(1024)",
		"field_types"=>"VARCHAR(1024)",
	),
	
	// session table
	"sessions" => Array(
		"expires" => "INTEGER NOT NULL",
		"session_data" => "TEXT",
	)
);

?>