<?php
function orderToCollector($information, $order = false)
{
    $this->load->model("admin/compra/compraapi_model", "compraapi");

    // Order
    $order = !$order ? $this->getCollectionByid('\Compra', $information['order_id']) : $order;

    // Data para proceso de busqueda
    $array = $this->prepareDataForAssignmentProcess($order);
    $array['petshop_category'] = 'collector';

    // Petshops
    $collectors = $this->petshopList($array);

    // Filtrar petshops
    $filters = isset($information['filters']) ? $information['filters'] : array("radio" => true, "horarios" => true, "productos" => true, "tiene_capacidad" => true, "delego" => false);
    $collectorsFiltered = $this->filterPetShops($collectors, $filters);

    $this->orm->getConnection()->beginTransaction(); // suspend auto-commit
    try {
        $order->setSeSortea(false);
        if (count($collectorsFiltered)) {

            // Petshop ganador
            $collectorWinner = $collectorsFiltered[0];

            $array_to_delegate = ['observaciones' => 'delegacion por colector asignado', 'proveedor_id' => $collectorWinner['puv_pro_id']];
            $send_notification = false;
            $responseDelegation = $this->compraapi->delegacionManual($order, $array_to_delegate, ADMIN_ID, $send_notification);
            $this->compra->cargaMovimientoCompraConsulta(array('usu_id' => ADMIN_ID, 'tipo' => 'consulta', 'motivo_consulta' => TIPO_DELEGACION_COLECTOR_ASIGNADO, 'com_id' => $order->getId(), 'textarea_cancela_motivo' => 'colector disponible asignado'));

            // Tag logistica colecta
            $order->setTreggoShippingId("colecta");
            $this->orm->persist($order);
            $this->orm->flush();

            $response = ['status' => true, 'message' => 'colector asignado correctamente: ' . $collectorWinner['puv_nombre']];
        } else {
            $response = ['status' => false, 'message' => 'no hay colectores disponibles que cumplan las condiciones del pedido'];
        }

        $this->orm->getConnection()->commit();
    } catch (Exception $ex) {
        $this->orm->close();
        $this->orm->getConnection()->rollBack();
        $response = ['status' => false, 'message' => $ex->getMessage()];
    }
    return $response;
}
