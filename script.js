$(document).ready(function(){  
  var table = $("#tablepedidos").dataTable({
            dom:`<lf<tr>ip>`,
            order: [5, "desc"],
            serverSide: true,
            processing: true,
            deferRender: true,
            lengthMenu: [ [10, 25, 50, 100], [10, 25, 50, 100] ],
            language: {
                url: "assets/DataTables/plug-ins/i18n/Spanish.json",
            },
            ajax:{
                url:'server-side.php',
                type: "post",
                data: function(d){
                    return $.extend({}, d, {
                    "fecha": moment(fecha, 'DD/MM/YYYY').format("YYYYMMDD"),
                    "reload": reload_table,
                });
                },
                dataSrc: function(json){
                    return json.data;
                }
            },
            columnDefs: [
                { 
                    targets: [3, 6, 7], 
                    visible: true, 
                    orderable: false
                },
                { 
                    targets: [8,9], 
                    visible: false, 
                },
            ],
            columns: [
                { "data": "code" },
                { "data": "sucursal" },
                { 
                    "data": "lastname",
                    "className": "",
                    render: function(data, type, full, meta){
                        var aux = data+" "+full.name;
                        aux = ucwords(aux);
                        return type === 'display' && aux.length > 30 ?
                            aux.substr( 0, 30 ) +'…' :
                            aux;
                    }
                }, 
                { 
                    "data": "null",
                    render: function(data, type, full, meta){
                        return full.cantidad;
                    }
                }, 
                { 
                    "data": "total",
                    render: function(data, type, full, meta){
                        if(type == 'display' || type == 'filter')
                            return number_format_js(data,2 ,'.' ,',');
                        else if(type == 'filter')
                            return data;
                        else 
                            return data;
                    } 
                },
                { 
                    "data": "fecha",
                    'className': 'fecha',
                    render: function(data, type, full, meta){
                        if(type == 'display')
                            return moment(data, "MM/DD/YYYY hh:mm:ss a").format("lll");
                        else
                            return data;
                    }
                },
                { "data": "estatus" },
                { 
                    "data": "null",
                    render: function(data, type, full, meta){
                        return `
                        <i class="material-icons pointer" Cid="`+full.customer_code+`" onclick="CargarCustomer('`+full.customer_code+`')">perm_contact_calendar</i>
                        <i class="material-icons pointer" Oid="`+full.code+`" onclick="CargarDatosOrden('`+full.code+`')">visibility</i>      
                        <!--<i class="material-icons pointer" Oid="`+full.code+`" onclick="CompletarOrden('`+full.code+`')">local_shipping</i>      -->
                        <i class="material-icons pointer" Oid="`+full.code+`" onclick="CancelarOrden('`+full.code+`')">delete</i>                  
                        <i class="material-icons pointer" Cid="`+full.code+`" onclick="CargarDetallePago('`+full.code+`')">payment</i> `;
                    }
                },
                { "data": "name" },
                { "data": "lastname" },
            ]
        });
})
