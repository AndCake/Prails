<?php

// first include the configuration
include "conf/configuration.php";
include "conf/database.php";

// include tools
include "lib/tools.php";
include "lib/logger.php";

// include database-classes
include "lib/db_entry.php";
include "lib/mysql.php";
include "lib/tblclass.php";
include "lib/abstract_database.php";
include "lib/condition.php";
include "lib/database.php";

// include output classes
include "lib/lang_data.php";
include "lib/jsmin.php";
include "lib/taglib.php";
include "lib/generator.php";

// include abstract event handler
include "lib/abstract_handler.php";

// include database deployer
include "conf/dbdeployer.php";

// include the main module
include "modules/main/main.php";

?>
