<?php

namespace App\Http\Controllers;

use App\Access;
use App\Helpers\HelperSigart;
use App\Models\Referenceterm;
use App\Models\ReferencetermDetail;
use App\Models\Service;
use App\Models\ServiceDetail;
use App\Models\ServicePaymentMethod;
use App\Models\SiteVourcher;
use App\SalesQuote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use PDF;

class ReferencetermController extends Controller
{
    const PATH_PDF_REFERENCE_TERM = '/reference/';

    protected $_moduleDB = 'vuex';
    protected $_page = 0;

    public function index( Request $request ) {

        $data = Referenceterm::whereHas( 'saleQuotation', function( $query ) {
            $query->where( 'status', 8 );
        })
            ->where( 'status', '!=', 2 )
            ->orderBy('created_at', 'desc')
            ->paginate( 20 );

        $references = [];
        foreach ( $data as $item ) {
            $row = new \stdClass();
            $row->id = $item->id;
            $row->accessRS = $this->permisionUser( 'sr' );
            $row->accessSO = $this->permisionUser( 'so' );

            $srUserAdm = $item->rtUserAdm;
            $srUserDG = $item->rtUserDG;
            $soUserDG = $item->soUserDG;
            $soUserCustomer = $item->soUserCustomer;

            $srtUserAdmName = '---';
            $srUserDGName = '---';
            $soUserDGName = '---';
            $soUserCustomerName = '---';

            if ( $srUserAdm ) {
                $srtUserAdmName = $srUserAdm->name . ' ' . $srUserAdm->last_name;
            }

            if( $srUserDG ) {
                $srUserDGName = $srUserDG->name . ' ' . $srUserDG->last_name;
            }

            if( $soUserDG ) {
                $soUserDGName = $soUserDG->name . ' ' . $soUserDG->last_name;
            }

            if( $soUserCustomer ) {
                $soUserCustomerName = $soUserCustomer->name . ' ' . $soUserCustomer->last_name;
            }

            $row->serviceRequirement = new \stdClass();
            $row->serviceRequirement->administration = new \stdClass();
            $row->serviceRequirement->administration->id = $item->rt_user_approved_adm;
            $row->serviceRequirement->administration->type = $this->typeAproved( $item->rt_type_approved_adm );
            $row->serviceRequirement->administration->user = $srtUserAdmName;
            $row->serviceRequirement->administration->show = $this->actionButton( 'sr', 'adm' );

            $row->serviceRequirement->generalDirection = new \stdClass();
            $row->serviceRequirement->generalDirection->id = $item->rt_user_approved_gd;
            $row->serviceRequirement->generalDirection->type = $this->typeAproved( $item->rt_type_approved_gd );
            $row->serviceRequirement->generalDirection->user = $srUserDGName;
            $row->serviceRequirement->generalDirection->show = $this->actionButton( 'sr', 'dg' );

            $row->serviceOrder = new \stdClass();
            $row->serviceOrder->generalDirection = new \stdClass();
            $row->serviceOrder->generalDirection->id = $item->os_user_approved_gd;
            $row->serviceOrder->generalDirection->type = $this->typeAproved( $item->os_type_approved_gd );
            $row->serviceOrder->generalDirection->user = $soUserDGName;
            $row->serviceOrder->generalDirection->show = $this->actionButton( 'so', 'dg' );

            $row->serviceOrder->customer = new \stdClass();
            $row->serviceOrder->customer->id = $item->os_user_approved_customer;
            $row->serviceOrder->customer->type = $this->typeAproved( $item-> os_type_approved_customer );
            $row->serviceOrder->customer->user = $soUserCustomerName;
            $row->serviceOrder->customer->show = $this->actionButton( 'so', 'cus' );
            $row->serviceOrder->customer->isCustomerLogin = false;
            if( $item->os_user_login_approved_customer > 0 ) {
                $soUserCustomerLogin = $item->soUserCustomerLogin;
                $srtUserCustomerLoginName = '';
                if( $soUserCustomerLogin ) {
                    $srtUserCustomerLoginName = $soUserCustomerLogin->name . ' ' . $soUserCustomerLogin->last_name;
                }

                $row->serviceOrder->customer->id = $item->os_user_approved_customer;
                $row->serviceOrder->customer->user = $srtUserCustomerLoginName;
                $row->serviceOrder->customer->isCustomerLogin = true;
            }

            $saleQuotation = $item->saleQuotation;
            $row->saleQuotation = new \stdClass();
            $row->saleQuotation->id = $saleQuotation->id;
            $row->saleQuotation->document = $saleQuotation->num_serie . '-' . $saleQuotation->num_doc;

            $serviceRequest = $saleQuotation->serviceRequest;
            $row->serviceRequest = new \stdClass();
            $row->serviceRequest->id = $serviceRequest->id;
            $row->serviceRequest->document = $serviceRequest->num_request;
            $row->serviceRequest->name = $serviceRequest->description;
            $row->serviceRequest->send = $serviceRequest->date_send ? date( 'd/m/Y', strtotime( $serviceRequest->date_send ) ) : '---';

            $customer = $item->customer;
            $name = $customer->name;
            if ($customer->type_person === 1) {
                $name .= ' ' . $customer->lastname;
            }
            $document = $customer->typeDocument->name . ': ' . $customer->document;

            $row->customer = new \stdClass();
            $row->customer->id = $customer->id;
            $row->customer->name = $name;
            $row->customer->document = $document;

            $row->documents = new \stdClass();
            $row->documents->pdfReferenceTerm = $item->pdf ? asset( self::PATH_PDF_REFERENCE_TERM . $item->pdf ) : '';
            $row->documents->pdfServiceRequirement= $item->pdf_rt ? asset( self::PATH_PDF_REFERENCE_TERM . $item->pdf_rt ) : '';
            $row->documents->pdfServiceOrder = ( $item->pdf_os && $item->rt_type_approved_adm === 1 && $item->rt_type_approved_gd == 1 ) ? asset( self::PATH_PDF_REFERENCE_TERM . $item->pdf_os ) : '';

            $service = $serviceRequest->services->whereNotIn('status', [0, 2])->sortByDesc('created_at')->first;
            $row->service = new \stdClass();
            $row->service->id = $service->id ? $service->id->id : 0;
            $row->service->sendOrderPay = $service->id ? $service->id->is_send_order_pay : 0;
            $references[] = $row;
        }

        $response = [
            'status' => true,
            'references' => $references,
            'pagination' => [
                'total' => $data->total(),
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem()
            ]
        ];

        return response()->json( $response );
    }

    private function permisionUser( $type ) {

        $role = Auth()->user()->role_id;

        $access = [
            'sr' => [ 1, 2, 7 ],
            'so' => [ 1, 7 ]
        ];

        if( in_array( $role, $access[$type] ) ) {
            return true;
        }

        return false;
    }

    private function actionButton( $doc, $type ) {

        $role = Auth()->user()->role_id;

        $access = [
            'sr' => [
                'adm' => [ 1, 2 ],
                'dg' => [ 1, 7 ]
            ],
            'so' => [
                'dg' => [ 1, 7 ],
                'cus' => [ 1, 2 ]
            ]
        ];

        if( in_array( $role, $access[$doc][$type] ) ) {
            return true;
        }

        return false;
    }

    private function typeAproved( $type ) {
        if( $type === 1 ) {
            return 'Aprobado';
        } elseif( $type === 2 ) {
            return 'Desaprobado';
        }
        return 'Por aprobar';
    }

    public function dashboard( Request $request ) {

        $saleQuotation = $request->saleQuotation ? $request->saleQuotation : 0;

        $breadcrumb = [
            [
                'name' => 'Terminos de referencia',
                'url' => route( 'reference-term.dashboard' )
            ],
            [
                'name' => 'Listado',
                'url' => '#'
            ]
        ];

        $permiso = Access::sideBar();

        return view('mintos.content', [
            'menu'          => $this->_page,
            'sidebar'       => $permiso,
            'moduleDB'      => $this->_moduleDB,
            'breadcrumb'    => $breadcrumb,
            'component'     => 'reference-term',
            'saleQuotation' => $saleQuotation
        ]);
    }

    public function generate( Request $request ) {

        $saleQuotation = $request->saleQuotation;

        $register = $this->register( $saleQuotation );

        return response()->json($register);
    }

    private function register( $saleQuotationId ) {

        $response = [
            'status' => false
        ];

        $exist = $this->existReferenceTerms( $saleQuotationId );

        if( $exist['status'] ) {

            $user = Auth()->user();
            $userId = $user->id;

            $saleQuotation = $exist['saleQuotation'];

            $customer = $saleQuotation->customer;

            $district = $saleQuotation->servicerequest->district_id ? $saleQuotation->servicerequest->district_id : $customer->district_id;
            $address = $saleQuotation->servicerequest->address ? $saleQuotation->servicerequest->address : $customer->address;

            $methodPayment = ServicePaymentMethod::find( $saleQuotation->service_payment_methods_id );

            $reference = $exist['reference'];
            $reference->sales_quotations_id = $saleQuotationId;
            $reference->customers_id = $saleQuotation->customers_id;
            $reference->area = $reference->_area;
            $reference->activity = $saleQuotation->activity;
            $reference->objective = $saleQuotation->objective;
            $reference->specialized_area = $reference->_specialized_area;
            $reference->execution_time_days = $saleQuotation->execution_time_days;
            $reference->execution_time_text = $reference->execution_time_text( $reference->execution_time_days );
            $reference->execution_address = $address;
            $reference->district_id = $district;
            $reference->method_payment = $methodPayment->description ? $methodPayment->description : '';
            $reference->conformance_grant = $reference->_conformance_grant;
            $reference->warranty_num = $saleQuotation->warranty_num;
            $reference->warranty_text = $reference->warranty_text( $saleQuotation->warranty_num );
            $reference->users_id_reg = $userId;
            $reference->sub_total = $saleQuotation->subtot_sale;
            $reference->igv = $saleQuotation->tot_igv;
            $reference->total = $saleQuotation->tot_gral;

            if( $reference->save() ) {
                $idReference = $reference->id;
                $this->registerDetails( $idReference, $saleQuotation->salesQuotationsDetails->where('status', 1) );
                $this->generatePdf( $reference );
                $this->generatePdf( $reference, 'service-requirement' );
//                $this->generatePdf( $reference, 'service-order' );

                $response['status'] = true;
            }

        }

        return $response;
    }

    private function existReferenceTerms( $saleQuotationId ) {

        $response = [
            'status' => false,
            'msg' => 'No se puede realizar la operación'
        ];

        $saleQuotation = SalesQuote::findOrFail( $saleQuotationId );

        if( $saleQuotation->status ===  8 ) {
            $reference = Referenceterm::where( 'status', '!=', 2 )
                ->where( 'sales_quotations_id', $saleQuotationId )
                ->first();

            $response['status'] = true;
            $response['saleQuotation'] = $saleQuotation;
            $response['type'] = 'EXIST';

            if( ! $reference ) {
                $reference = new Referenceterm();
                $response['type'] = 'NEW';
            }

            $response['reference'] = $reference;
        }

        return $response;
    }

    private function registerDetails( $reference, $details ) {

        ReferencetermDetail::where( 'status', 1 )
            ->where( 'referenceterms_id', $reference )
            ->update(['status' => 2 ]);

        if( count( $details ) ) {
            foreach( $details as $detail ) {
                $referenceDetail = new ReferencetermDetail();
                $referenceDetail->referenceterms_id = $reference;
                $referenceDetail->description = $detail->description;
                $referenceDetail->quantity = $detail->quantity;
                $referenceDetail->total = $detail->total;
                $referenceDetail->save();

            }
        }
        return true;
    }

    public function getDataReferenceTerm( $reference ) {

        $getReference = new \stdClass();
        $getReference->id = $reference->id;
        $getReference->area = $reference->area;
        $getReference->activity = $reference->activity;
        $getReference->objective = $reference->objective;
        $getReference->specializedArea = $reference->specialized_area;
        $getReference->daysExecution = $reference->execution_time_text;
        $getReference->daysExecutionV2 = $reference->execution_time_days > 1 ? $reference->execution_time_days . ' Días' : $reference->execution_time_days . ' Día';
        $getReference->executionAddress = $reference->execution_address;
        $getReference->addressReference = $reference->address_reference;
        $getReference->methodPayment = $reference->method_payment;
        $getReference->conformanceGrant = $reference->conformance_grant;
        $getReference->warranty = $reference->warranty_text;
        $getReference->obervations = $reference->obervations;
        $getReference->details = $reference->referencetermDetails;
        $getReference->ubigeo = HelperSigart::ubigeo( $reference->district_id, 'inline' );
        $getReference->dateSRApproved = new \stdClass();
        $getReference->dateSRApproved->year = $reference->created_at ? date( 'Y' ,strtotime( $reference->created_at ) ) : '';
        $getReference->dateSRApproved->month = $reference->created_at ? date( 'm' ,strtotime( $reference->created_at ) ) : '';
        $getReference->dateSRApproved->day = $reference->created_at ? date( 'd' ,strtotime( $reference->created_at ) ) : '';
        $getReference->dateSOApproved = $reference->rt_date_approved_gd ? date( 'd/m/Y' ,strtotime( $reference->rt_date_approved_gd ) ) : '';
        $getReference->srDocument = $reference->sr_serie;
        $getReference->srDocumentNum = $reference->sr_number;
        $getReference->soDocument = $reference->so_serie;
        $getReference->soDocumentNum = $reference->so_number;
        $getReference->total = $reference->total;

        $customer = $reference->customer;
        $name = $customer->name;
        if ($customer->type_person === 1) {
            $name .= ' ' . $customer->lastname;
        }

        $getReference->customer = $customer->document . ' - ' . $name;
        $getReference->typeDocument = $customer->typeDocument->name;
        $getReference->numero = $customer->document;
        $getReference->addressCustomer = $customer->address . ' - ' . HelperSigart::ubigeo( $customer->district_id, 'inline' );;

        $getReference->saleQuotation = $reference->saleQuotation->num_serie . '-' . $reference->saleQuotation->num_doc;
        return $getReference;
    }

    private function generatePdf( $reference, $type = 'reference-term' ) {
        $response = [
            'status' => false
        ];

        if( $reference ) {

            $title = 'TÉRMINO DE REFERENCIA';
            $template = 'mintos.PDF.pdf-reference-terms';
            switch ( $type ) {
                case 'service-requirement':
                    $title = 'Requerimiento de servicio';
                    $template = 'mintos.PDF.pdf-service-requirement';
                    break;
                case 'service-order':
                    $title = 'Orden de servicio';
                    $template = 'mintos.PDF.pdf-service-order';
                    break;
            }

            $getReference = $this->getDataReferenceTerm( $reference );

            $data = [
                'title' => $title,
                'reference' => $getReference
            ];

            $filename   = Str::slug( $title. '-' . $reference->id ) . '.pdf';
            $path       = public_path() . self::PATH_PDF_REFERENCE_TERM . $filename;

            $pdf = \App::make('dompdf.wrapper');
            $pdf->getDomPDF()->set_option("enable_php", true);
            $pdf->loadView( $template, $data );
            $pdf->save( $path );

            $permit = true;
            if( $type === 'reference-term' ) {
                $reference->pdf = $filename;
            } elseif ( $type === 'service-requirement' ) {
                $reference->pdf_rt = $filename;
            } elseif( $type === 'service-order' ) {
                $reference->pdf_os = $filename;
            } else {
                $permit = false;
            }

            if( $permit ) {
                $reference->save();
            }

            $response['path'] = $path;
            $response['filename'] = $filename;
            $response['title'] = $title;
        }

        return $response;
    }

    public function getData( Request $request ) {

        $response = [
            'status' => false,
            'msg' => 'No se encontro el término de referencia para la cotización seleccionada.'
        ];

        $saleQuotation = $request->saleQuotation ? $request->saleQuotation : 0;

        $saleQuotationData = SalesQuote::findOrfail( $saleQuotation );
        if( $saleQuotationData->status === 8 ) {

            $dateDelivery = $saleQuotationData->serviceRequest->delivery_date;
            $dateDelivery = $dateDelivery ? date( 'd/m/Y', strtotime( $dateDelivery ) ) : '';

            $referenceTerm = Referenceterm::where( 'sales_quotations_id', $saleQuotation )
                ->where( 'status', '!=', 2 )
                ->first();

            if( $referenceTerm ) {
                $response['status'] = true;

                $response['reference'] = new \stdClass();
                $response['reference']->id = $referenceTerm->id;
                $response['reference']->area = $referenceTerm->area;
                $response['reference']->activity = $referenceTerm->activity;
                $response['reference']->delivery = $dateDelivery;
                $response['reference']->objective = $referenceTerm->objective;
                $response['reference']->specializedArea = $referenceTerm->specialized_area;
                $response['reference']->daysExecution = $referenceTerm->execution_time_days;
                $response['reference']->executionAddress = $referenceTerm->execution_address;
                $response['reference']->addressReference = $referenceTerm->address_reference;
                $response['reference']->methodPayment = $referenceTerm->method_payment;
                $response['reference']->conformanceGrant = $referenceTerm->conformance_grant;
                $response['reference']->warrantyMonth = $referenceTerm->warranty_num;
                $response['reference']->obervations = $referenceTerm->obervations;
                $response['reference']->pdf = asset( self::PATH_PDF_REFERENCE_TERM . $referenceTerm->pdf );
                $response['reference']->pdfSR = asset( self::PATH_PDF_REFERENCE_TERM . $referenceTerm->pdf_rt );
                $response['reference']->pdfSO = asset( self::PATH_PDF_REFERENCE_TERM . $referenceTerm->pdf_os );
                $response['reference']->details = [];
                $response['reference']->customer = new \stdClass();

                $customer = $referenceTerm->customer;
                $name = $customer->name;
                if ($customer->type_person === 1) {
                    $name .= ' ' . $customer->lastname;
                }
                $document = $customer->typeDocument->name . ': ' . $customer->document;

                $response['reference']->customer->id = $customer->id;
                $response['reference']->customer->name = $name;
                $response['reference']->customer->document = $document;

                $ubigeo = HelperSigart::ubigeo( $referenceTerm->district_id );
                $response['reference']->ubigeo = new \stdClass();
                $response['reference']->ubigeo->district = $ubigeo['district_id'] ? $ubigeo['district_id'] : '0';
                $response['reference']->ubigeo->province = $ubigeo['province_id'] ? $ubigeo['province_id'] : '0';
                $response['reference']->ubigeo->departament = $ubigeo['departament_id'] ? $ubigeo['departament_id'] : '0';

                $details = $referenceTerm->referencetermDetails->where('status', 1);

                foreach( $details as $detail ) {
                    $row = new \stdClass();
                    $row->id = $detail->id;
                    $row->description = $detail->description;
                    $row->quantity = $detail->quantity;
                    $row->total = $detail->total;

                    $response['reference']->details[] = $row;
                }
            }
        }

        return response()->json( $response );
    }

    public function update( Request $request ) {
        $id = $request->id;
        $daysExecution = $request->daysExecution ? $request->daysExecution : 1;
        $warrantyMonth = $request->warrantyMonth ? $request->warrantyMonth : 12;

        $reference = Referenceterm::findOrFail( $id );
        $reference->activity = $request->activity;
        $reference->objective = $request->objective;
        $reference->execution_time_days = $daysExecution;
        $reference->execution_time_text = $reference->execution_time_text( $daysExecution );
        $reference->execution_address = $request->executionAddress;
        $reference->district_id = $request->district;
        $reference->address_reference = $request->addressReference;
        $reference->method_payment = $request->methodPayment;
        $reference->warranty_num = $warrantyMonth;
        $reference->warranty_text = $reference->warranty_text( $warrantyMonth );;
        $reference->obervations = $request->obervations;
        if( ! $reference->sr_serie && ! $reference->sr_number ) {
            $typeVoucher        = 9;
            $SiteVoucherClass   = new SiteVourcher();
            $correlative        = $SiteVoucherClass->getNumberVoucherSite( $typeVoucher, 'details' );
            if( $correlative['status'] ) {
                $reference->sr_serie = $correlative['correlative']['serie'];
                $reference->sr_number = $correlative['correlative']['number'];
                $SiteVoucherClass->increaseCorrelative($typeVoucher);
            }
        }

        if( $reference->save() ) {
            $this->generatePdf( $reference );
            $this->generatePdf( $reference, 'service-requirement' );
            return response()->json([
                'status' => true
            ]);
        }
        return response()->json([
            'status' => false
        ]);
    }

    public function action( Request $request ) {
        $id = $request->id;
        $action = $request->action;
        $type = $request->type;
        $typeAdm = $request->typeAdm;

        $User = Auth()->user();
        $userId = $User->id;

        $reference = Referenceterm::find( $id );
        $SiteVoucherClass   = new SiteVourcher();

        if( $reference &&  $reference->status === 1 ) {

            if( $type === 'sr' && $this->permisionUser( $type ) ) {
                if( $typeAdm === 'adm' ) {
                    $reference->rt_type_approved_adm = $action === 'approved' ? 1 : 2;
                    $reference->rt_date_approved_adm = date( 'Y-m-d H:i:s' );
                    $reference->rt_user_approved_adm = $userId;
                }

                if( $typeAdm === 'gd' && $reference->rt_type_approved_adm === 1 ) {
                    $reference->rt_type_approved_gd = $action === 'approved' ? 1 : 2;
                    $reference->rt_date_approved_gd = date( 'Y-m-d H:i:s' );
                    $reference->rt_user_approved_gd = $userId;

                    $typeVoucher        = 10;
                    $correlative        = $SiteVoucherClass->getNumberVoucherSite( $typeVoucher, 'details' );
                    $reference->so_serie = $correlative['correlative']['serie'];
                    $reference->so_number = $correlative['correlative']['number'];
                    $this->generatePdf( $reference, 'service-order' );
                    $SiteVoucherClass->increaseCorrelative($typeVoucher);
                }
            }

            if( $type === 'so' && $this->permisionUser( $type ) ) {
                if( $typeAdm === 'gd' && $reference->rt_type_approved_adm === 1 && $reference->rt_type_approved_gd === 1 ) {
                    $reference->os_type_approved_gd = $action === 'approved' ? 1 : 2;
                    $reference->os_date_approved_gd = date( 'Y-m-d H:i:s' );
                    $reference->os_user_approved_gd = $userId;
                    $this->createService( $reference );
                }
                if( $typeAdm === 'customer' && $reference->os_type_approved_gd === 1 ) {
                    $reference->os_type_approved_customer = $action === 'approved' ? 1 : 2;
                    $reference->os_date_approved_customer = date( 'Y-m-d H:i:s' );
                    $reference->os_user_approved_customer = $userId;

                    $customer = $reference->customer;
                    if( $customer && $customer->email ) {
                        $template = 'quotation-request';
                        $vars = [
                            'name' => $customer->name
                        ];
                        $this->sendMail($customer->email, 'Solicitud de orden de servicio', $template, $vars);
                    }
                }
            }

            $reference->save();

        }

        return response()->json([
            'status' => true
        ]);
    }

    private function createService( Referenceterm $reference ) {
        if( $reference ) {

            $user = Auth::user();

            $saleQuotation = $reference->saleQuotation;
            $serviceRequest = $saleQuotation->serviceRequest;

            $service = new Service();
            $service->service_requests_id = $serviceRequest->id;
            $service->serial_doc = $reference->so_serie;
            $service->number_doc = $reference->so_number;
            $service->user_reg = $user->id;
            $service->user_aproved = $user->id;
            $service->date_reg = date( 'Y-m-d' );
            $service->date_aproved = date( 'Y-m-d' );
            $service->sub_total = $reference->sub_total;
            $service->igv = $reference->igv;
            $service->total = $reference->total;
            $service->description = $reference->objective;
            $service->save();

            $details = $reference->referencetermDetails->where( 'status', 1 );
            $this->registerServiceDetail( $service->id, $details );
        }
        return true;
    }

    private function registerServiceDetail( $service, $details ) {

        foreach ( $details as $detail ) {
            $serviceDetail = new ServiceDetail();
            $serviceDetail->services_id = $service;
            $serviceDetail->description = $detail->description;
            $serviceDetail->price_unit = $detail->price_unit;
            $serviceDetail->quantity = $detail->quantity;
            $serviceDetail->total = $detail->total;
            $serviceDetail->save();
        }

        return true;
    }
}
