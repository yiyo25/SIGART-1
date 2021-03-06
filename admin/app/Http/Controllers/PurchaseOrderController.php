<?php

namespace App\Http\Controllers;

use App\Access;
use App\Models\InputOrder;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\PurchaseOrderDetail;
use App\Models\SiteVourcher;
use App\Provider;
use App\PurchaseOrder;
use App\Quotation;
use App\QuotationDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use PDF;

class PurchaseOrderController extends Controller
{
    protected $_moduleDB    = 'purchase-order';
    protected $_page        = 20;

    const PATH_PDF_PURCHASE_ORDER = '/pdf/purchase-order/';

    public function dashboard() {
        $breadcrumb = [
            [
                'name' => 'Ordenes de compra (OC)',
                'url' => route( 'purchase-order.index' )
            ],
            [
                'name' => 'Listado',
                'url' => '#'
            ]
        ];

        $permiso = Access::sideBar( $this->_page );
        return view('mintos.content', [
            'menu'          => $this->_page,
            'sidebar'       => $permiso,
            'moduleDB'      => $this->_moduleDB,
            'breadcrumb'    => $breadcrumb,
            'assetUrl'      => asset( self::PATH_PDF_PURCHASE_ORDER )
        ]);
    }

    public function generate( Request $request ) {
        if( ! $request->ajax() ) return redirect( '/' );

        $response = [
            'status' => false,
            'msg' => ''
        ];

        $quotation = Quotation::findOrFail( $request->id );

        if( $quotation->status === 4 ) {

            $user_id    = Auth::user()->id;
            $cant       = 0;
            $total      = 0;

            $purchaseOrder = new PurchaseOrder();
            $purchaseOrder->sites_id        = 1;/*ID de la sede principal*/
            $purchaseOrder->provider_id     = $quotation->providers_id;
            $purchaseOrder->quotations_id   = $request->id;
            $purchaseOrder->user_reg        = $user_id;
            $purchaseOrder->code            = 'S/C';
            $purchaseOrder->date_reg        = date( 'Y-m-d' );
            $purchaseOrder->status          = 0;

            if( $purchaseOrder->save() ) {
                $purchaseOrderId = $purchaseOrder->id;

                $quotationDetails = QuotationDetail::where('status', 1)
                    ->where('selected', 1)
                    ->where('quotations_id', $request->id)
                    ->get();

                if( $quotationDetails ) {
                    foreach ( $quotationDetails as $details ){
                        $purchaseOrderDetail = new PurchaseOrderDetail();
                        $purchaseOrderDetail->purchase_orders_id = $purchaseOrderId;
                        $purchaseOrderDetail->presentation_id = $details->presentation_id;
                        $purchaseOrderDetail->quantity = $details->quantity;
                        $purchaseOrderDetail->price_unit = $details->unit_price;
                        $purchaseOrderDetail->sub_total = $details->total;/*ya no se usara*/
                        $purchaseOrderDetail->igv = 0;/*Solo se usara en la tabla Purchase order*/
                        $purchaseOrderDetail->total = $details->total;
                        if( $purchaseOrderDetail->save() ) {
                            $cant++;
                            $total += $details->total;
                        }
                    }
                }
            }

            if( $cant > 0 ) {
                $subTotal   = ( 100 / 118 ) * $total;
                $igv        = ( $this::IGV * $subTotal );
                $purchaseOrder->status  = 1;
                $purchaseOrder->subtotal    = round( $subTotal, 2 );
                $purchaseOrder->igv         = round( $igv, 2 );
                $purchaseOrder->total       = round( $total, 2 );

                $quotation->status = 5;
                $quotation->save();

                if( $purchaseOrder->save() ) {
                    $response['status'] = true;
                    $response['msg']    = 'OK';

                    $this->logAdmin( 'Orden de compra generado ID::' . $purchaseOrder->id );
                } else {
                    $response['msg']    = 'Error al intentar guardar los datos.';
                    $this->logAdmin( 'No se pudo actualizar orden de compra ID::' . $purchaseOrder->id );
                }

            } else {
                $response['msg']    = 'La cotización no tiene items.';
                $this->logAdmin( 'Orden de compra anulado por falta de items ID::' . $purchaseOrder->id );
            }

        } else {
            $response['msg'] = 'No se pudo realizar la operación.';
        }

        return response()->json( $response );
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index( Request $request )
    {
        if( ! $request->ajax() ) return redirect( '/' );

        $response   = [];
        $numPerPage = 20;
        $search     = $request->search;
        $statusReq  = $request->status ? $request->status : 1;
        $status     = [1];

        switch ( $statusReq ) {
            case 2:
                $status = [
                    3, 4
                ];
                break;
            case 3:
                $status = [
                    2
                ];
        }

        $data = PurchaseOrder::where('purchase_orders.sites_id', 1)
                    ->whereIn('purchase_orders.status', $status)
                    ->search( $search )
                    ->join('providers', 'providers.id', 'purchase_orders.provider_id')
                    ->orderBy('purchase_orders.id', 'desc')
                    ->select(
                        'purchase_orders.id',
                        'purchase_orders.code',
                        'purchase_orders.date_reg',
                        'purchase_orders.subtotal',
                        'purchase_orders.igv',
                        'purchase_orders.total',
                        'purchase_orders.status',
                        'purchase_orders.pdf',
                        'providers.name',
                        'providers.business_name',
                        'providers.type_person',
                        'providers.document',
                        'providers.type_document',
                        'providers.type_document as statusProvider'
                    )
                    ->paginate( $numPerPage );
        $response['pagination'] = [
            'total'         => $data->total(),
            'current_page'  => $data->currentPage(),
            'per_page'      => $data->perPage(),
            'last_page'     => $data->lastPage(),
            'from'          => $data->firstItem(),
            'to'            => $data->lastItem()
        ];
        $response['records'] = $data;

        return response()->json( $response );

    }

    public function approve( Request $request ) {
        if( ! $request->ajax() ) return redirect('/');

        $user = Auth::user();

        $response = [
            'status'    => false,
            'msg'       => ''
        ];

        $purchaseOrder = PurchaseOrder::findOrFail( $request->id );
        $objProvider = Provider::findOrFail( $purchaseOrder->provider_id );

        if( $purchaseOrder->status  ) {//status === 1

            $typeVoucher        = 3;
            $SiteVoucherClass   = new SiteVourcher();
            $correlative        = $SiteVoucherClass->getNumberVoucherSite( $typeVoucher );

            if( $correlative['status'] ) {
                $purchaseOrder->code = $correlative['correlative'];
                $purchaseOrder->status = 3;

                $purchase = new Purchase();
                $purchase->purchase_orders_id = $request->id;
                $purchase->type_vouchers_id = 1;
                $purchase->type_payment_methods_id = 1;
                $purchase->serial_doc = '';
                $purchase->number_doc = '';
                $purchase->status = 0;
                $purchase->save();

                $inputOrder = new InputOrder();
                $inputOrder->purchases_id = $purchase->id;
                $inputOrder->code = '';
                $inputOrder->date_input_reg = date('Y-m-d H:i:s');
                $inputOrder->user_reg = $user->id;
                $inputOrder->date_input = date('Y-m-d');
                $inputOrder->save();

                $purchaseOrderDetails = PurchaseOrderDetail::where('status', 1)
                    ->where('purchase_orders_id', $request->id)
                    ->select('presentation_id', 'quantity', 'price_unit', 'sub_total', 'igv', 'total')
                    ->get();

                foreach ($purchaseOrderDetails as $pod) {
                    $purchaseDetail = new PurchaseDetail();
                    $purchaseDetail->purchases_id = $purchase->id;
                    $purchaseDetail->presentation_id = $pod->presentation_id;
                    $purchaseDetail->quantity = $pod->quantity;
                    $purchaseDetail->price_unit = $pod->price_unit;
                    $purchaseDetail->sub_total = $pod->sub_total;
                    $purchaseDetail->igv = $pod->igv;
                    $purchaseDetail->total = $pod->total;
                    $purchaseDetail->save();
                }

                if ($purchaseOrder->save()) {

                    $SiteVoucherClass->increaseCorrelative($typeVoucher);

                    $detail = $this->getDetails( $purchaseOrder->id );
                    $pdf = $this->generatePDF( $purchaseOrder, $objProvider, $detail );

                    if( $objProvider->email ) {
                        $template = 'quotation-request';
                        $vars = [
                            'name' => $objProvider->name
                        ];
                        $attach = self::PATH_PDF_PURCHASE_ORDER . $pdf['filename'];
                        $this->sendMail( $objProvider->email, $pdf['title'], $template, $vars, '', $attach );
                    }

                    $response['status'] = true;
                    $response['msg'] = 'OK';
                    $this->logAdmin('Orden de compra aprobada ID::' . $purchaseOrder->id);

                } else {

                    $response['msg'] = 'No se pudo aprobar la orden de compra.';
                    $this->logAdmin('No se pudo aprobar la orden de compra ID::' . $purchaseOrder->id);

                }
            } else {
                $response['msg'] = 'Falta crear el correlativo para los comprobantes.';
                $this->logAdmin('Falta crear el correlativo para los comprobantes.' );
            }

        } else {
            $response['msg'] = 'No se pudo realizar la operación';
            $this->logAdmin('Intentó aprobar la Orden de compra ID::' . $purchaseOrder->id );
        }

        return response()->json( $response );
    }

    public function getDetails( $id ) {

        return PurchaseOrderDetail::where('purchase_order_details.status', 1)
            ->where('purchase_order_details.purchase_orders_id', $id)
            ->where( 'presentation.status', 1 )
            ->where( 'unity.status', 1 )
            ->join('presentation', 'presentation.id', '=', 'purchase_order_details.presentation_id')
            ->join('unity', 'unity.id', '=', 'presentation.unity_id')
            ->leftJoin('products', 'products.id', '=', 'presentation.products_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->select(
                'purchase_order_details.id',
                'purchase_order_details.purchase_orders_id',
                'purchase_order_details.presentation_id',
                'purchase_order_details.quantity',
                'purchase_order_details.price_unit',
                'purchase_order_details.total',
                'presentation.description',
                'products.name as product',
                'categories.name as category',
                'unity.name as unity'
            )
            ->get();

    }

    public function generatePDF( $objPO, $objProvider, $details ) {

        $provider = $objProvider->name;
        $title = 'Orden de Compra N° ' . $objPO->code;
        $data = [
            'title'     => $title,
            'typePerson'=> $objProvider->type_person,
            'provider'  => $provider,
            'code'      => $objPO->code,
            'details'   => $details
        ];

        $filename   = Str::slug( $title. '-' . $objProvider->id ) . '.pdf';
        $path       = public_path() . self::PATH_PDF_PURCHASE_ORDER . $filename;
        $pdf        = PDF::loadView( 'mintos.PDF.pdf-quotation-approved', $data);
        $pdf->save( $path );


        $objPO->pdf = $filename;
        $objPO->save();

        return [
            'path'      => $path,
            'filename'  => $filename,
            'title'     => $title
        ];
    }

    public function generatePDFRequest( Request $request ) {

        $purchaseOrder = PurchaseOrder::findOrFail( $request->id );
        $objProvider = Provider::findOrFail( $purchaseOrder->provider_id );
        $detail = $this->getDetails( $purchaseOrder->id );

        $pdf = $this->generatePDF( $purchaseOrder, $objProvider, $detail );
        return response()->json([
            'status'    => true,
            'filename'  => $pdf['filename'],
            'path'      => $pdf['path']
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show( Request $request )
    {
        $id = $request->id;
        $purchaseOrder = PurchaseOrder::findOrFail( $id );
        $objProvider    = Provider::findOrFail( $purchaseOrder->provider_id );
        $detail         = $this->getDetails( $id );

        return response()->json([
            'status' => true,
            'info' => [
                'purchaseOrder' => [
                    'code' => $purchaseOrder->code,
                    'date' => date( 'd/m/Y', strtotime( $purchaseOrder->date_reg ) ),
                    'pdf' => asset( self::PATH_PDF_PURCHASE_ORDER . $purchaseOrder->pdf ),
                    'status' => $purchaseOrder->status
                ],
                'provider' => [
                    'name' => $objProvider->name,
                    'doc'   => $objProvider->document
                ],
                'details' => $detail
            ]
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy( Request $request )
    {
        $purchaseOrder = PurchaseOrder::findOrFail( $request->id );
        $purchaseOrder->status = 2;
        $purchaseOrder->save();
        return response()->json([
            'status' => true
        ]);
    }

    public function forwardMail( Request $request ) {

        $purchaseOrder  = PurchaseOrder::findOrFail( $request->id );
        $objProvider    = Provider::findOrFail( $purchaseOrder->provider_id );
        $detail         = $this->getDetails( $request->id );
        $pdf            = $this->generatePDF( $purchaseOrder, $objProvider, $detail );

        if( $objProvider->email ) {
            $template = 'quotation-request';
            $vars = [
                'name' => $objProvider->name
            ];
            $attach = $pdf['filename'] !== '' ? self::PATH_PDF_PURCHASE_ORDER . $pdf['filename'] : '';
            $this->sendMail( $objProvider->email, $pdf['title'], $template, $vars, '', $attach );
            $this->logAdmin('Orden de compra - Se reenvió correctamente ID::' . $purchaseOrder->id );
        } else {
            $this->logAdmin('Orden de compra - No se puede reenviar debidó a que el proveedor no cuenta con email ID::' . $purchaseOrder->id );
        }

        $response['status'] = true;
        $response['msg'] = 'OK';

        return response()->json( $response );
    }
}
