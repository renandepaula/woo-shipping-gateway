<?php
/**
 * Fix para integração Frenet + Tiny/Olist
 *
 * Este snippet corrige o problema onde o ERP Tiny/Olist estava usando
 * o method_id "frenet" como nome da transportadora, ao invés de usar
 * o identificador técnico único (FRENET_ID) para mapear a transportadora correta.
 *
 * Como usar:
 * 1. Copie este código para o Fluent Snippets ou functions.php do seu tema
 * 2. Ative o snippet
 * 3. Teste enviando um pedido ao ERP
 *
 * O que este código faz:
 * - Intercepta o webhook antes de enviar ao Tiny/Olist
 * - Busca o FRENET_ID nos meta_data de cada shipping_line
 * - Substitui o method_id de "frenet" para o FRENET_ID (ex: "FRENET_FMT_WS_1")
 * - Mantém o instance_id intacto
 */

/**
 * Modifica o payload do webhook para usar o FRENET_ID como method_id
 *
 * @param array $payload O payload que será enviado via webhook
 * @param string $resource O tipo de recurso (order, product, etc)
 * @param int $resource_id O ID do recurso
 * @param int $webhook_id O ID do webhook
 * @return array O payload modificado
 */
function fix_frenet_method_id_for_tiny_webhook( $payload, $resource, $resource_id, $webhook_id ) {
    // Só processa se for um pedido
    if ( $resource !== 'order' ) {
        return $payload;
    }

    // Verifica se há shipping_lines no payload
    if ( ! isset( $payload['shipping_lines'] ) || ! is_array( $payload['shipping_lines'] ) ) {
        return $payload;
    }

    // Percorre cada shipping_line
    foreach ( $payload['shipping_lines'] as $key => $shipping_line ) {
        // Verifica se é método Frenet
        if ( isset( $shipping_line['method_id'] ) && $shipping_line['method_id'] === 'frenet' ) {

            // Busca o FRENET_ID nos meta_data
            if ( isset( $shipping_line['meta_data'] ) && is_array( $shipping_line['meta_data'] ) ) {
                foreach ( $shipping_line['meta_data'] as $meta ) {
                    if ( isset( $meta['key'] ) && $meta['key'] === 'FRENET_ID' && isset( $meta['value'] ) ) {
                        // Substitui o method_id pelo FRENET_ID
                        $payload['shipping_lines'][$key]['method_id'] = $meta['value'];

                        // Log para debug (opcional - pode comentar se não precisar)
                        if ( function_exists( 'wc_get_logger' ) ) {
                            $logger = wc_get_logger();
                            $logger->info(
                                sprintf(
                                    'Frenet Fix: Pedido #%d - method_id alterado de "frenet" para "%s"',
                                    $resource_id,
                                    $meta['value']
                                ),
                                array( 'source' => 'frenet-tiny-fix' )
                            );
                        }

                        break; // Encontrou o FRENET_ID, não precisa continuar o loop
                    }
                }
            }
        }
    }

    return $payload;
}
add_filter( 'woocommerce_webhook_payload', 'fix_frenet_method_id_for_tiny_webhook', 10, 4 );

/**
 * Também modifica o payload da REST API para manter consistência
 *
 * @param WP_REST_Response $response A resposta da API
 * @param WC_Order $order O objeto do pedido
 * @param WP_REST_Request $request A requisição
 * @return WP_REST_Response A resposta modificada
 */
function fix_frenet_method_id_for_tiny_rest_api( $response, $order, $request ) {
    $data = $response->get_data();

    // Verifica se há shipping_lines nos dados
    if ( ! isset( $data['shipping_lines'] ) || ! is_array( $data['shipping_lines'] ) ) {
        return $response;
    }

    // Percorre cada shipping_line
    foreach ( $data['shipping_lines'] as $key => $shipping_line ) {
        // Verifica se é método Frenet
        if ( isset( $shipping_line['method_id'] ) && $shipping_line['method_id'] === 'frenet' ) {

            // Busca o FRENET_ID nos meta_data
            if ( isset( $shipping_line['meta_data'] ) && is_array( $shipping_line['meta_data'] ) ) {
                foreach ( $shipping_line['meta_data'] as $meta ) {
                    if ( isset( $meta['key'] ) && $meta['key'] === 'FRENET_ID' && isset( $meta['value'] ) ) {
                        // Substitui o method_id pelo FRENET_ID
                        $data['shipping_lines'][$key]['method_id'] = $meta['value'];
                        break;
                    }
                }
            }
        }
    }

    $response->set_data( $data );
    return $response;
}
add_filter( 'woocommerce_rest_prepare_shop_order_object', 'fix_frenet_method_id_for_tiny_rest_api', 10, 3 );
