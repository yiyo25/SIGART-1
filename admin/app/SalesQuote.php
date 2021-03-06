<?php

namespace App;

use App\Models\SiteVourcher;
use Illuminate\Database\Eloquent\Model;
use DB;

class SalesQuote extends Model
{
    const TABLE_NAME = 'sales_quotations';
    const CANCELED_STATE = [0, 2, 5, 7, 9, 10];

    protected $table = self::TABLE_NAME;

    public function serviceRequest() {
        return $this->belongsTo( 'App\Models\ServiceRequest', 'service_requests_id', 'id' );
    }

    public function salesQuotationsDetails() {
        return $this->hasMany( 'App\Models\SalesQuotationsDetails', 'sales_quotations_id', 'id' );
    }

    public function userFirst() {
        return $this->belongsTo( 'App\User', 'user_reply', 'id' );
    }

    public function userSecond() {
        return $this->belongsTo( 'App\User', 'user_reply_second', 'id' );
    }

    public function customer() {
        return $this->belongsTo( 'App\Customer', 'customers_id', 'id');
    }

    public function customerLogin() {
        return $this->belongsTo( 'App\Models\CustomerLogin', 'customer_login_id', 'id');
    }

    public function referenceterms() {
        return $this->hasMany( 'App\Models\Referenceterm', 'sales_quotations_id' );
    }

    public function servicePaymentMethod() {
        return $this->belongsTo( 'App\Models\ServicePaymentMethod', 'service_payment_methods_id' );
    }

	public static function List_Type_Documents()
    {
    	$Resultado = DB::select("SELECT * FROM type_vouchers WHERE status = '1' ");
    	return $Resultado;
    }

    public static function List_Customers()
    {
    	$Resultado = DB::select("SELECT * FROM customers WHERE status = '1' ");
    	return $Resultado;
    }

    public static function Generate_Num_Serie()
    {
        $Resultado = DB::selectOne("
            SELECT val1 AS num_serie FROM parametros WHERE `status` = '1' AND `group` = 'SERIE' AND id = '8'
        ");
        return $Resultado;
    }

    public static function Generate_Num_Document()
    {
        $Resultado = DB::selectOne("
            SELECT right(concat('000000000000',(IFNULL(Max(RIGHT(num_doc,10)),0)+1)),10) as num_doc
            FROM sales_quotations
        ");
        return $Resultado;
    }


    public static function ListTypeServices()
    {
        $Resultado = DB::select("SELECT * FROM type_services WHERE status = '1' ");
        return $Resultado;
    }

    public static function List_Products_x_TypeService($arrayCampos = [])
    {
        $Resultado = DB::select("
            SELECT * FROM products
            WHERE status = '1'
            AND cod_type_service = :cod_type_service
        ", $arrayCampos);
        return $Resultado;
    }

    public static function List_Unitys()
    {
        $Resultado = DB::select("SELECT * FROM unity WHERE status = '1' ");
        return $Resultado;
    }


    public static function View_Data_Product_x_ID($arrayCampos = [])
    {
        $Resultado = DB::selectOne("
            SELECT * FROM presentation where `status` = '1' AND products_id = :products_id
        ", $arrayCampos);
        return $Resultado;
    }


    public static function List_Dsctos()
    {
        $Resultado = DB::select("
            SELECT * FROM parametros P WHERE P.status = '1' AND P.group = 'DESCUENTO'
        ");
        return $Resultado;
    }

    public static function Data_IGV()
    {
        $Resultado = DB::selectOne("
            SELECT * FROM parametros P WHERE P.`status` = '1' AND P.`group` = 'IGV' AND P.id = '1'
        ");
        return $Resultado;
    }

    public static function Generate_ID_Cotizacion()
    {
        $Resultado = DB::selectOne("
            SELECT (IFNULL(Max(id),0)+1) as id_cotizacion FROM sales_quotations
        ");
        return $Resultado;
    }


    public static function Registrar_Cotizacion_CAB($arrayCampos = [])
    {
        $Resultado = DB::insert("
            INSERT INTO sales_quotations (
                id,
                type_vouchers_id,
                date_emission,
                num_serie,
                num_doc,
                customers_id,
                tot_sale,
                porc_dscto,
                tot_dscto,
                subtot_sale,
                porc_igv,
                tot_igv,
                tot_gral,
                total_letter,
                observation,
                status,
                created_at,
                updated_at
            ) VALUES(
                :id,
                :type_vouchers_id,
                :date_emission,
                :num_serie,
                :num_doc,
                :customers_id,
                :tot_sale,
                :porc_dscto,
                :tot_dscto,
                :subtot_sale,
                :porc_igv,
                :tot_igv,
                :tot_gral,
                :total_letter,
                :observation,
                '1',
                NOW(),
                NOW()
            )
        ",$arrayCampos);

        return $Resultado;
    }




    public static function Data_Empresa()
    {
        $Resultado = DB::selectOne("
        SELECT
        (SELECT val1 FROM parametros WHERE `status` = '1' AND `group` = 'EMPRESA' AND id = '15' ) AS RUC,
        (SELECT val1 FROM parametros WHERE `status` = '1' AND `group` = 'EMPRESA' AND id = '16' ) AS NOM_EMPRESA,
        (SELECT val1 FROM parametros WHERE `status` = '1' AND `group` = 'EMPRESA' AND id = '17' ) AS NOM_COMERCIAL,
        (SELECT val1 FROM parametros WHERE `status` = '1' AND `group` = 'EMPRESA' AND id = '18' ) AS DIRECCION,
        (SELECT val1 FROM parametros WHERE `status` = '1' AND `group` = 'EMPRESA' AND id = '19' ) AS DEPA,
        (SELECT val1 FROM parametros WHERE `status` = '1' AND `group` = 'EMPRESA' AND id = '20' ) AS PROV,
        (SELECT val1 FROM parametros WHERE `status` = '1' AND `group` = 'EMPRESA' AND id = '21' ) AS DIST,
        (SELECT val1 FROM parametros WHERE `status` = '1' AND `group` = 'EMPRESA' AND id = '22' ) AS TELEF
        ");
        return $Resultado;
    }



    public static function Data_sales_quotations_x_ID_CAB($arrayCampos = [])
    {
        $Resultado = DB::selectOne("
            SELECT
            sq.id,
            sq.type_vouchers_id,
            tv.`name` as documento,
            DATE_FORMAT(sq.date_emission, '%d/%m/%Y') as fecha_emis,
            sq.num_serie,
            sq.num_doc,
            sq.customers_id,
            concat(c.`name`, '', c.lastname ) as cliente,
            sq.tot_sale,
            sq.porc_dscto,
            sq.tot_dscto,
            sq.subtot_sale,
            sq.porc_igv,
            sq.tot_igv,
            sq.tot_gral,
            sq.total_letter,
            sq.observation
            FROM sales_quotations sq
            LEFT JOIN type_vouchers tv ON (tv.id = sq.type_vouchers_id)
            LEFT JOIN customers c ON (c.id = sq.customers_id)
            WHERE sq.id = :id_cab
        ",$arrayCampos);
        return $Resultado;
    }




    public static function Generate_ID_Cotizacion_Details()
    {
        $Resultado = DB::selectOne("
            SELECT (IFNULL(Max(id),0)+1) as codigo FROM sales_quotations_details
        ");
        return $Resultado;
    }





    public static function Registrar_Cotizacion_DET($arrayCampos = [])
    {
        $Resultado = DB::insert("
            INSERT INTO sales_quotations_details (
                id,
                sales_quotations_id,
                quantity,
                unity_id,
                products_id,
                coment,
                unit_price,
                total,
                status,
                created_at,
                updated_at

            ) VALUES(
                :id,
                :sales_quotations_id,
                :quantity,
                :unity_id,
                :products_id,
                :coment,
                :unit_price,
                :total,
                '1',
                NOW(),
                NOW()
            )
        ",$arrayCampos);

        return $Resultado;
    }




    public static function Data_sales_quotations_x_ID_DET($arrayCampos = [])
    {
        $Resultado = DB::select("
            SELECT
            SQD.id,
            SQD.sales_quotations_id,
            SQD.quantity,
            SQD.unity_id,
            U.`name` as Unidad,
            SQD.products_id,
            P.`name` as producto,
            SQD.coment,
            SQD.unit_price,
            SQD.total
            FROM sales_quotations_details SQD
            LEFT JOIN products P ON (P.id = SQD.products_id)
            LEFT JOIN unity U ON (U.id = SQD.unity_id)
            WHERE SQD.sales_quotations_id = :id_cab
            AND SQD.`status` = '1'
        ",$arrayCampos);
        return $Resultado;
    }

    /*
     * sales_quotations: status
     * 0: desactivado -
     * 1: Creado
     * 2: Eliminado -
     * 3: 1° solicitud de aprobación
     * 4: 1° Aprobación ( 2° solicitud de aprobación )
     * 5: 1° Rechazado -
     * 6: 2° Aprobación ( Aprobación Cliente )
     * 7: 2° Rechazo -
     * 8: 3° Aprobación ( para estructura de servicio )
     * 9: 2° Rechazo -
     * */

    public static function generateSalesQuotation( $idServiceRequest ) {

        $response = [
            'status' => false,
            'new' => false
        ];

        $salesQuotation = self::whereNotIn( 'status', self::CANCELED_STATE )
            ->where( 'service_requests_id', $idServiceRequest )
            ->where( 'is_approved_customer', 0 )
            ->where( 'customer_login_id', 0 )
            ->first();

        if( ! $salesQuotation ) {
            $serviceRequest = ServiceRequest::whereNotIn( 'status', [0, 2] )
                ->where( 'id', $idServiceRequest )
                ->first();

            if( ! $serviceRequest ) {
                $response['msg'] = 'Hubo un problema al realizar la cotización.';
                return $response;
            }

            $typeVoucher        = 8;
            $SiteVoucherClass   = new SiteVourcher();
            $correlative        = $SiteVoucherClass->getNumberVoucherSite( $typeVoucher, 'details' );

            if( $correlative['status'] ) {
                $salesQuotation = new SalesQuote();
                $salesQuotation->date_emission = date('Y-m-d');
                $salesQuotation->activity = $serviceRequest->description;
                $salesQuotation->objective = $serviceRequest->description;
                $salesQuotation->service_payment_methods_id = 1;
                $salesQuotation->type_vouchers_id = $typeVoucher;
                $salesQuotation->num_serie = $correlative['correlative']['serie'];
                $salesQuotation->num_doc = $correlative['correlative']['number'];
                $salesQuotation->service_requests_id = $idServiceRequest;
                $salesQuotation->customers_id = $serviceRequest->customers_id;
                $salesQuotation->is_approved_customer = 0;
                $salesQuotation->customer_login_id = 0;
                $salesQuotation->status = 1;
                if ($salesQuotation->save()) {
                    $response['new'] = true;
                    $SiteVoucherClass->increaseCorrelative($typeVoucher);

                    $response['msg'] = 'OK';
                } else {
                    $response['msg'] = 'Falta correlativo de documento.';
                    return $response;
                }
            } else {
                $response['msg'] = 'Falta correlativo de documento.';
                return $response;
            }
        }

        $sqId = $salesQuotation->id;
        $response['status'] = true;
        $response['saleQuotation'] = $sqId;
        $response['collection'] = $salesQuotation;
        return $response;
    }

}
