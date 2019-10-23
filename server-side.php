/*
 * DataTables example server-side processing script.
 *
 * Please note that this script is intentionally extremely simply to show how
 * server-side processing can be implemented, and probably shouldn't be used as
 * the basis for a large complex system. It is suitable for simple use cases as
 * for learning.
 *
 * See http://datatables.net/usage/server-side for full details on the server-
 * side processing requirements of DataTables.
 *
 * @license MIT - http://datatables.net/license_mit
 */

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * Easy set variables
 */

// DB table to use
$table = 'ORDERS_MA ORD';

// Table's primary key
$primaryKey = 'ORD.Code';

// JOIN table
$join = " INNER JOIN CUSTOMERS_MA CUST ON ORD.CustomerCode=CUST.Code 
          INNER JOIN ORDERS_TR TR ON TR.Code=ORD.Code 
          INNER JOIN ORDERS_MA_STATUS ORDS ON ORDS.Code=ORD.Status
          INNER JOIN MA_SUCURSALES MS ON MS.C_codigo=ORD.StoreCode           
          INNER JOIN ORDERS_PAYMENTMETHOD OP ON OP.Code=ORD.Code
          INNER JOIN ORDERS_PAYMENTMETHOD_TYPES OPT ON OPT.Code=OP.Type";

//WHERE

$fecha_i = $_POST['fecha'].' 00:00:00.000';
$fecha_f = $_POST['fecha'].' 23:59:59.000';

if($_POST['reload'] == 'true')
	$where = "ORD.STATUS IN (1,2) AND ORD.Date BETWEEN '$fecha_i' AND '$fecha_f'";
else
	$where = "ORD.STATUS IN (1,2)";

//GROUP BY
$group_by = "GROUP BY ORD.Code,CUST.Lastname,CUST.Name,ORD.Total,ORD.DATE,CUST.Code,ORDS.SysName,MS.c_descripcion,OPT.Description";

// Array of database columns which should be read and sent back to DataTables.
// The `db` parameter represents the column name in the database, while the `dt`
// parameter represents the DataTables column identifier. In this case simple
// indexes

// SQL server connection information
$sql_details = array(
    'user' => $myUser,
    'pass' => $myPass,
    'db'   => $myDB,
    'host' => $myServer,
);


/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * If you just want to use the basic configuration for DataTables with PHP
 * server-side, there is no need to edit below this line.
 */

require("../../assets/DataTables/server_side/scripts/ssp.mssql_adodb.class.php");


$columns = array(
    array( 'db' => 'ORD.Code', 'dt' => 'code', 'as' => "code"),
    array( 'db' => 'CUST.Name', 'dt' => 'name', 'as' => 'name' ),
    array( 'db' => 'CUST.Lastname', 'dt' => 'lastname', 'as' => 'lastname' ),
    array( 'db' => 'ORD.Total', 'dt' => 'total', 'as' => 'total' ),
    array( 'db' => 'ORD.Date', 'dt' => 'fecha', 'as' => 'fecha' ),
    array( 'db' => 'CUST.Code', 'dt' => 'customer_code', 'as' => 'customer_code' ),
    array( 'db' => 'ORDS.SysName', 'dt' => 'estatus', 'as' => 'estatus' ),
    array( 'db' => 'MS.c_descripcion', 'dt' => 'sucursal', 'as' => 'sucursal' ),
    array( 'db' => 'OPT.Description', 'dt' => 'f_pago', 'as' => 'f_pago' ),
    array( 'db' => 'ROUND(SUM(Quantity),0)', 'dt' => 'cantidad', 'as' => 'cantidad' ),
);

$arr__ = json_encode(SSP::complex( $_POST, $sql_details, $table, $primaryKey, $columns, $join, null, $where, $group_by ));

if(!$arr__)
	echo json_last_error_msg();
else
	echo $arr__;
?>
