<?php

/*
 * Helper functions for building a DataTables server-side processing SQL query
 *
 * The static functions in this class are just helper functions to help build
 * the SQL used in the DataTables demo server-side processing scripts. These
 * functions obviously do not represent all that can be done with server-side
 * processing, they are intentionally simple to show how it works. More complex
 * server-side processing operations will likely require a custom script.
 *
 * See http://datatables.net/usage/server-side for full details on the server-
 * side processing requirements of DataTables.
 *
 * @license MIT - http://datatables.net/license_mit
 */


// REMOVE THIS BLOCK - used for DataTables test environment only!
$file = $_SERVER['DOCUMENT_ROOT'].'/datatables/pdo.php';
if ( is_file( $file ) ) {
	include( $file );
}


class SSP {
	/**
	 * Create the data output array for the DataTables rows
	 *
	 *  @param  array $columns Column information array
	 *  @param  array $data    Data from the SQL get
	 *  @return array          Formatted data in a row based format
	 */
	
	static function data_output ( $columns, $data )
	{
		$out = array();

		for ( $i=0, $ien=count($data) ; $i<$ien ; $i++ ) {
			$row = array();

			for ( $j=0, $jen=count($columns) ; $j<$jen ; $j++ ) {
				$column = $columns[$j];

				// Is there a formatter?
				if ( isset( $column['formatter'] ) ) {
					if ( isset( $column['as'] ) ) {
						$row[ $column['dt'] ] = $column['formatter']( $data[$i][ $column['as'] ], $data[$i] );
					} else {
						$row[ $column['dt'] ] = $column['formatter']( $data[$i][ $column['db'] ], $data[$i] );
					}
				}
				else {
					if ( isset( $column['as'] ) ) {
						$row[ $column['dt'] ] = $data[$i][ $columns[$j]['as'] ];
					} else {
						$row[ $column['dt'] ] = $data[$i][ $columns[$j]['db'] ];
					}
				}
			}

			$out[] = $row;
		}

		return $out;
	}


	/**
	 * Database connection
	 *
	 * Obtain an PHP PDO connection from a connection details array
	 *
	 *  @param  array $conn SQL connection details. The array should have
	 *    the following properties
	 *     * host - host name
	 *     * db   - database name
	 *     * user - user name
	 *     * pass - user password
	 *  @return resource PDO connection
	 */
	static function db ( $conn )
	{
		if ( is_array( $conn ) ) {
			return self::sql_connect( $conn );
		}

		return $conn;
	}


	/**
	 * Paging
	 *
	 * Construct the LIMIT clause for server-side processing SQL query
	 *
	 *  @param  array $request Data sent to server by DataTables
	 *  @param  array $columns Column information array
	 *  @return string SQL limit clause
	 */
	static function limit ( $request, $columns ) {
	 	$limit = '';
	 	if ( isset($request['start']) && $request['length'] != -1 ) {
	  		$limit = "ORDER BY [LINE] OFFSET ".intval($request['start'])." ROWS FETCH NEXT ".intval($request['length'])." ROWS ONLY";
	  	}
		// limit and order conflict when using sql server.
		// so duplicate the functionality in ORDER and switch on/off as needed based on ORDER
	  	if ( isset($request['order'])) {
	   		$limit = '';    // if there is an ORDER request then clear the limit
	   		return $limit;    // because the ORDER function will handle the LIMIT
	  	}
	  	else
	  	{
	  		return $limit;
	  	}
	}


	/**
	 * Ordering
	 *
	 * Construct the ORDER BY clause for server-side processing SQL query
	 *
	 *  @param  array $request Data sent to server by DataTables
	 *  @param  array $columns Column information array
	 *  @return string SQL order by clause
	 */
	
	static function order ( $request, $columns ) {
	  	$order = '';
	  	if ( isset($request['order']) && count($request['order']) ) {
		    $orderBy = array();
		    $dtColumns = self::pluck( $columns, 'dt' );
	    	for ( $i=0, $ien=count($request['order']) ; $i<$ien ; $i++ ) {
	      		// Convert the column index into the column data property
	      		$columnIdx = intval($request['order'][$i]['column']);
	      		$requestColumn = $request['columns'][$columnIdx];
	      		$columnIdx = array_search( $requestColumn['data'], $dtColumns );
	      		$column = $columns[ $columnIdx ];
	      		if ( $requestColumn['orderable'] == 'true' ) {
	        		$dir = $request['order'][$i]['dir'] === 'asc' ?
	         		'ASC' :
	         		'DESC';
	         		$orderBy[] = ''.$column['db'].' '.$dir;   // revised for SQL Server
	      		}
	    	}
	  		// see "static function limit" above to explain the next line.
	  		$order =  "ORDER BY ".implode(', ', $orderBy)." OFFSET ".intval($request['start'])." ROWS FETCH NEXT ".intval($request['length'])." ROWS ONLY";
	  	}
	  	return $order;
	}


	/**
	 * Searching / Filtering
	 *
	 * Construct the WHERE clause for server-side processing SQL query.
	 *
	 * NOTE this does not match the built-in DataTables filtering which does it
	 * word by word on any field. It's possible to do here performance on large
	 * databases would be very poor
	 *
	 *  @param  array $request Data sent to server by DataTables
	 *  @param  array $columns Column information array
	 *  @param  array $bindings Array of values for PDO bindings, used in the
	 *    sql_exec() function
	 *  @return string SQL where clause
	 */
	static function filter ( $request, $columns, &$bindings )
	{
		$globalSearch = array();
		$columnSearch = array();
		$dtColumns = self::pluck( $columns, 'dt' );

		if ( isset($request['search']) && $request['search']['value'] != '' ) {
			$str = $request['search']['value'];

			for ( $i=0, $ien=count($request['columns']) ; $i<$ien ; $i++ ) {
				$requestColumn = $request['columns'][$i];
				$columnIdx = array_search( $requestColumn['data'], $dtColumns );
				$column = $columns[ $columnIdx ];

				if ( $requestColumn['searchable'] == 'true' ) {
					// $binding = self::bind( $bindings, '%'.$str.'%', PDO::PARAM_STR );
					// $globalSearch[] = "".$column['db']." LIKE ".$binding;
					$globalSearch[] = "".$column['db']." LIKE '%".$str."%'";
				}
			}
		}

		// Individual column filtering
		if ( isset( $request['columns'] ) ) {
			for ( $i=0, $ien=count($request['columns']) ; $i<$ien ; $i++ ) {
				$requestColumn = $request['columns'][$i];
				$columnIdx = array_search( $requestColumn['data'], $dtColumns );
				$column = $columns[ $columnIdx ];

				$str = $requestColumn['search']['value'];
				
				if(isset( $requestColumn['search']['not'] ) && $requestColumn['search']['not'] == 'true')
					$not = "true";
				else 
					$not = "false";

				if ( $requestColumn['searchable'] == 'true' && $str != '' ) {
				 	if(substr_count($str,";") > 0 && $not == "true")
		            {
		                 $WhereStr = strpos($str,";");
		                 $SearchColumn = "(" .$column['db']. " NOT LIKE '" . substr($str,0,$WhereStr). "'";
		                 for($x = 1;$x < substr_count($str,";");$x++)
		                 {
		                     $SearchColumn = $SearchColumn. " AND " .$column['db']. " NOT LIKE '" . substr($str,$WhereStr+1,strpos($str,";",$WhereStr+1) - ($WhereStr+1)). "'";
		                     $WhereStr = strpos($str,";",$WhereStr+1);
		                 }
		                 $SearchColumn = $SearchColumn. " AND " .$column['db']. " NOT LIKE '" . substr($str,$WhereStr+1). "')";
		                 $columnSearch[] = $SearchColumn;
		            }
				 	else if(substr_count($str,";") > 0)
		            {
		                 $WhereStr = strpos($str,";");
		                 $SearchColumn = "(" .$column['db']. " LIKE '" . substr($str,0,$WhereStr). "'";
		                 for($x = 1;$x < substr_count($str,";");$x++)
		                 {
		                     $SearchColumn = $SearchColumn. " or " .$column['db']. " LIKE '" . substr($str,$WhereStr+1,strpos($str,";",$WhereStr+1) - ($WhereStr+1)). "'";
		                     $WhereStr = strpos($str,";",$WhereStr+1);
		                 }
		                 $SearchColumn = $SearchColumn. " or " .$column['db']. " LIKE '" . substr($str,$WhereStr+1). "')";
		                 $columnSearch[] = $SearchColumn;
		            }
					else if($str != "null" && $not == "true"){
						$columnSearch[] = "".$column['db']." NOT LIKE '".$str."'";
					} 
					else if($str != "null"){
						// $binding = self::bind( $bindings, '%'.$str.'%', PDO::PARAM_STR );
						//$binding = self::bind( $bindings, $str, PDO::PARAM_STR );
						$columnSearch[] = "".$column['db']." LIKE '".$str."'";
					} else {
						$columnSearch[] = "".$column['db']." IS NULL";
					}
				}
			}
		}

		// Combine the filters into a single string
		$where = '';

		if ( count( $globalSearch ) ) {
			$where = '('.implode(' OR ', $globalSearch).')';
		}

		if ( count( $columnSearch ) ) {
			$where = $where === '' ?
				implode(' AND ', $columnSearch) :
				$where .' AND '. implode(' AND ', $columnSearch);
		}

		if ( $where !== '' ) {
			$where = 'WHERE '.$where;
		}

		return $where;
	}


	/**
	 * Perform the SQL queries needed for an server-side processing requested,
	 * utilising the helper functions of this class, limit(), order() and
	 * filter() among others. The returned array is ready to be encoded as JSON
	 * in response to an SSP request, or can be modified if needed before
	 * sending back to the client.
	 *
	 *  @param  array $request Data sent to server by DataTables
	 *  @param  array|PDO $conn PDO connection resource or connection parameters array
	 *  @param  string $table SQL table to query
	 *  @param  string $primaryKey Primary key of the table
	 *  @param  array $columns Column information array
	 *  @return array          Server-side processing response array
	 */
	static function simple ( $request, $sql_details, $table, $primaryKey, $columns ) {
		$bindings = array();
		$db = self::sql_connect( $sql_details );
		// Build the SQL query string from the request
		$limit = self::limit( $request, $columns );
		$order = self::order( $request, $columns );
		$where = self::filter( $request, $columns, $bindings );
		 
		// Main query to actually get the data
		$data = self::sql_exec( $db, $bindings,"SET NOCOUNT ON SELECT ".implode(", ", self::pluck($columns, 'db'))." FROM $table $where $order $limit" );
		  
		// Data set length after filtering  the $where will update info OR will be blank when not doing a search
		$resFilterLength = self::sql_exec( $db, $bindings,"SET NOCOUNT ON SELECT ".implode(", ", self::pluck($columns, 'db'))." FROM $table $where " );
		$recordsFiltered = count($resFilterLength);
		 
		// Total data set length
		$resTotalLength = self::sql_exec( $db,"SET NOCOUNT ON SELECT COUNT(*) FROM $table" );
		$recordsTotal = $resTotalLength[0][0];
		          
		/*  Output   */
		return array(
			"draw"            => intval( $request['draw'] ),
		    "recordsTotal"    => intval( $recordsTotal ),
		    "recordsFiltered" => intval( $recordsFiltered ),
		    "data"            => self::data_output( $columns, $data )
		);
	}


	/**
	 * The difference between this method and the `simple` one, is that you can
	 * apply additional `where` conditions to the SQL queries. These can be in
	 * one of two forms:
	 *
	 * * 'Result condition' - This is applied to the result set, but not the
	 *   overall paging information query - i.e. it will not effect the number
	 *   of records that a user sees they can have access to. This should be
	 *   used when you want apply a filtering condition that the user has sent.
	 * * 'All condition' - This is applied to all queries that are made and
	 *   reduces the number of records that the user can access. This should be
	 *   used in conditions where you don't want the user to ever have access to
	 *   particular records (for example, restricting by a login id).
	 *
	 *  @param  array $request Data sent to server by DataTables
	 *  @param  array|PDO $conn PDO connection resource or connection parameters array
	 *  @param  string $table SQL table to query
	 *  @param  string $primaryKey Primary key of the table
	 *  @param  array $columns Column information array
	 *  @param  string $whereResult WHERE condition to apply to the result set
	 *  @param  string $whereAll WHERE condition to apply to all queries
	 *  @return array          Server-side processing response array
	 */
	
	static function complex ( $request, $conn, $table, $primaryKey, $columns, $join=null, $whereResult=null, $whereAll, $group_by = null )
    {
        $bindings = array();
        $db = self::db( $conn );
        $localWhereResult = array();
        $localWhereAll = array();
        $whereAllSql = '';
 
        // Build the SQL query string from the request
        $limit = self::limit( $request, $columns );
        $order = self::order( $request, $columns );
        $where = self::filter( $request, $columns, $bindings );
 
        $whereResult = self::_flatten( $whereResult );
        $whereAll = self::_flatten( $whereAll );
 
        if ( $whereResult ) {
            $where = $where ?
                $where .' AND '.$whereResult :
                'WHERE '.$whereResult;
        }
 
        if ( $whereAll ) {
            $where = $where ?
                $where .' AND '.$whereAll :
                'WHERE '.$whereAll;
 
            //$whereAllSql = 'WHERE '.$whereAll;
        }
    
        // Main query to actually get the data
     	if($join != null){
     		
     		$data = self::sql_exec( $db, $bindings,
		        "SET NOCOUNT ON SELECT ".implode(", ", self::pluck($columns, 'db','as'))." FROM $table $join $where $group_by $order $limit", $columns );
	        

     		if($group_by != null){
	        	$resFilterLength = self::sql_exec( $db, $bindings,
	        	"SELECT COUNT(*) OVER () FROM $table $join $where $group_by");
	 			if($resFilterLength == null)
	 				$recordsFiltered = 0;
				else
					$recordsFiltered = $resFilterLength[0][0];

	        	// Total data set length
	    		$resTotalLength = self::sql_exec( $db,"SELECT COUNT(*) OVER () FROM $table $join $group_by" );
	  			if($resTotalLength == null)
	 				$recordsTotal = 0;
				else
					$recordsTotal = $resTotalLength[0][0];

	  			//$recordsTotal = $resTotalLength[0][0];
	  		} else {
	  			$resFilterLength = self::sql_exec( $db, $bindings,
	        	"SELECT count({$primaryKey}) FROM $table $join $where" );
	 	  		$recordsFiltered = $resFilterLength[0][0];
	 
	        	// Total data set length
	    		$resTotalLength = self::sql_exec( $db,"SELECT COUNT({$primaryKey}) FROM $table $join" );
	  			$recordsTotal = $resTotalLength[0][0];
	  		}

     	} else {

     		$data = self::sql_exec( $db, $bindings,
		        "SET NOCOUNT ON SELECT ".implode(", ", self::pluck($columns, 'db'))." FROM $table $where $order $limit", $columns );
     		$resFilterLength = self::sql_exec( $db, $bindings,
	        "SELECT count({$primaryKey}) FROM $table $where" );
	 	  	$recordsFiltered = $resFilterLength[0][0];
	 
	        // Total data set length
	    	$resTotalLength = self::sql_exec( $db,"SELECT COUNT({$primaryKey}) FROM $table" );
	  		$recordsTotal = $resTotalLength[0][0];

     	}

 		//"SET NOCOUNT ON SELECT * FROM $table $where $order $limit" );
 		
        // Data set length after filtering
 
        /*
         * Output
         */
        return array(
            "draw"            => isset ( $request['draw'] ) ?
                intval( $request['draw'] ) :
                0,
            "recordsTotal"    => $recordsTotal,
            "recordsFiltered" => $recordsFiltered,
            "data"            => self::data_output( $columns, $data )
        );
    }

	/**
	 * Connect to the database
	 *
	 * @param  array $sql_details SQL server connection details array, with the
	 *   properties:
	 *     * host - host name
	 *     * db   - database name
	 *     * user - user name
	 *     * pass - user password
	 * @return resource Database connection handle
	 */

	static function sql_connect ( $sql_details ){
        try {
        	$db = new COM ("ADODB.Connection")  or die("Cannot start ADO");
		  	$db->open(
		  			"PROVIDER=SQLOLEDB;SERVER=".$sql_details['host'].";
		  			 UID=".$sql_details['user'].";
		  			 PWD=".$sql_details['pass'].";
		  			 DATABASE=".$sql_details['db']); 
        }
        catch (COM_Exception $e) {
            self::fatal(
                "An error occurred while connecting to the database. ".
                "The error reported by the server was: ".$e->getMessage()
            );
        }
 
        return $db;
	}


	/**
	 * Execute an SQL query on the database
	 *
	 * @param  resource $db  Database handler
	 * @param  array    $bindings Array of PDO binding values from bind() to be
	 *   used for safely escaping strings. Note that this can be given as the
	 *   SQL query string if no bindings are required.
	 * @param  string   $sql SQL query to execute.
	 * @return array         Result from the query (all rows)
	 */
	static function sql_exec ( $db, $bindings, $sql=null, $columns = null )
	{
		$out = array();
		if ( $sql === null ) {
			$sql = $bindings;
		}

		// echo $sql;
		//$stmt = $db->prepare($sql);

		// Bind parameters
		// if ( is_array( $bindings ) ) {
		// 	for ( $i=0, $ien=count($bindings) ; $i<$ien ; $i++ ) {
		// 		$binding = $bindings[$i];
		// 		$db->bind( $stmt, $binding['key'] );
		// 		$db->bind( $stmt, $binding['val'] );
		// 		$db->bind( $stmt, $binding['type'] );
		// 	}
		// }

		// Execute
		try {
			$r = $db->execute($sql);
			
			while(!$r->EOF){
				if($columns != null){
					$row = array();
					for ($i=0; $i < count($columns); $i++) { 
						if(isset($columns[$i]['as']))
							$row[$columns[$i]['as']] = "".$r->fields($columns[$i]['as'])->value;
						else
							$row[$columns[$i]['db']] = "".$r->fields($columns[$i]['db'])->value;
					}
				} else {
					$row[] = $r->fields(0)->value;
				}

				$out[] = $row;
				$r->MoveNext();
			}
		}
		catch (COM_Exception $e) {
			self::fatal( "An SQL error occurred: ".$e->getMessage() );
		}

		// Return all
		return $out;
	}


	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
	 * Internal methods
	 */

	/**
	 * Throw a fatal error.
	 *
	 * This writes out an error message in a JSON string which DataTables will
	 * see and show to the user in the browser.
	 *
	 * @param  string $msg Message to send to the client
	 */
	static function fatal ( $msg )
	{
		echo json_encode( array( 
			"error" => $msg
		) );

		exit(0);
	}

	/**
	 * Create a PDO binding key which can be used for escaping variables safely
	 * when executing a query with sql_exec()
	 *
	 * @param  array &$a    Array of bindings
	 * @param  *      $val  Value to bind
	 * @param  int    $type PDO field type
	 * @return string       Bound key to be used in the SQL where this parameter
	 *   would be used.
	 */
	static function bind ( &$a, $val, $type )
	{
		$key = ':binding_'.count( $a );

		$a[] = array(
			'key' => $key,
			'val' => $val,
			'type' => $type
		);

		return $key;
	}


	/**
	 * Pull a particular property from each assoc. array in a numeric array, 
	 * returning and array of the property values from each item.
	 *
	 *  @param  array  $a    Array to get data from
	 *  @param  string $prop Property to read
	 *  @return array        Array of property values
	 */
	static function pluck ( $a, $prop, $alias = "")
	{
		$out = array();

		for ( $i=0, $len=count($a) ; $i<$len ; $i++ ) {
			if(!$alias == "") {
				$out[] = $a[$i][$prop] . ' AS ' . $a[$i][$alias] . "\r\n";
			} else {
				$out[] = $a[$i][$prop];
			}
		}

		return $out;
	}


	/**
	 * Return a string from an array or a string
	 *
	 * @param  array|string $a Array to join
	 * @param  string $join Glue for the concatenation
	 * @return string Joined string
	 */
	static function _flatten ( $a, $join = ' AND ' )
	{
		if ( ! $a ) {
			return '';
		}
		else if ( $a && is_array($a) ) {
			return implode( $join, $a );
		}
		return $a;
	}

	// HERE I have appended the modified SSP.PHP source fragments EVERYTHING WORKS
	//
}

