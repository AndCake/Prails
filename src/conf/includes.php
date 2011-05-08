<?php
/**
    PRails Web Framework
    Copyright (C) 2010  Robert Kunze

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

// first include the configuration
include "conf/configuration.php";
include "conf/database.php";

// include tools
include "lib/tools.php";
include "lib/quartz.php";
include "lib/debug/logger.php";
if (ENV_PRODUCTION !== true) {
	include "lib/debug/debugger.php";
}

// include database-classes
include "lib/db_entry.php";
include "lib/cacheable.php";
include "lib/".strtolower(DB_TYPE).".php";
include "lib/tblclass.php";
include "lib/sessionmgr.php";
include "lib/database.php";

// include output classes
include "lib/hookcore.php";
include "lib/lang_data.php";
include "lib/jsmin.php";
include "lib/taglib.php";
include "lib/lessc.php";
include "lib/csslib.php";
include "lib/generator.php";

// include abstract event handler
include "lib/abstract_handler.php";

// include database deployer
include "conf/dbdeployer.php";

// include the main module
include "modules/main/main.php";

?>
