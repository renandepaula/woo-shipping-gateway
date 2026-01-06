# Fix para Integração WooCommerce + Frenet + Tiny/Olist

## 📋 Entendendo o Problema

### O que estava acontecendo?

1. **Plugin Frenet** calcula o frete e retorna opções de transportadoras (FM Transportes, Correios, etc)
2. **WooCommerce** salva o pedido com estas informações:
   - `method_id`: "frenet" (sempre fixo - é o ID da classe do método de envio)
   - `method_title`: "Econômico (em até 12 dias úteis)" (o que o cliente vê)
   - `instance_id`: "177" (ID da instância específica na zona de envio)
   - `meta_data.FRENET_ID`: "FRENET_FMT_WS_1" (identificador técnico único da transportadora)

3. **ERP Tiny/Olist** recebe o webhook/API e **usa o `method_id` para definir o nome da transportadora**
4. Como o `method_id` é sempre "frenet", o ERP criava uma transportadora chamada "frenet" ao invés de usar "FM Transportes"

### Por que isso acontecia?

O Tiny/Olist estava usando o campo **errado** para identificar a transportadora:
- ❌ Usava: `method_id` = "frenet" (gateway de cálculo)
- ✅ Deveria usar: `FRENET_ID` = "FRENET_FMT_WS_1" (identificador da transportadora real)

Segundo a Karine do suporte Olist:
> "Para a definição do nome da Transportadora, o sistema SEMPRE utiliza a informação contida no campo method_id."

**Conclusão**: O ERP não estava configurado para buscar o identificador técnico nos `meta_data`, então precisamos **adaptar o payload** para que o `method_id` contenha o valor correto.

---

## 🔧 A Solução

### Como funciona o fix?

O código em `fix-frenet-tiny-integration.php` faz o seguinte:

1. **Intercepta** o webhook antes de enviar ao Tiny/Olist
2. **Busca** o valor de `FRENET_ID` nos `meta_data` de cada shipping_line
3. **Substitui** o `method_id` de "frenet" para o valor do `FRENET_ID`
4. **Mantém** todos os outros campos intactos (instance_id, method_title, etc)

### Exemplo prático:

**ANTES (payload original):**
```json
{
  "shipping_lines": [
    {
      "method_id": "frenet",
      "method_title": "Econômico (em até 12 dias úteis)",
      "instance_id": "177",
      "meta_data": [
        {
          "key": "FRENET_ID",
          "value": "FRENET_FMT_WS_1"
        }
      ]
    }
  ]
}
```

**DEPOIS (payload modificado):**
```json
{
  "shipping_lines": [
    {
      "method_id": "FRENET_FMT_WS_1",
      "method_title": "Econômico (em até 12 dias úteis)",
      "instance_id": "177",
      "meta_data": [
        {
          "key": "FRENET_ID",
          "value": "FRENET_FMT_WS_1"
        }
      ]
    }
  ]
}
```

Agora o Tiny/Olist consegue mapear corretamente a transportadora usando o `method_id` = "FRENET_FMT_WS_1" que você configurou no mapeamento!

---

## 📥 Como Instalar

### Opção 1: Via Fluent Snippets (Recomendado)

1. Acesse **WordPress Admin** → **Snippets** → **Add New**
2. Copie todo o conteúdo do arquivo `fix-frenet-tiny-integration.php`
3. Cole no editor do Fluent Snippets
4. Dê um nome: "Fix Integração Frenet + Tiny"
5. **Ative** o snippet
6. Pronto!

### Opção 2: Via functions.php do tema

1. Acesse **Aparência** → **Editor de Temas**
2. Abra o arquivo `functions.php` do seu tema ativo
3. Cole o código no **final do arquivo**
4. Salve
5. Pronto!

⚠️ **ATENÇÃO**: Se atualizar o tema, o código será perdido. Use um tema filho ou prefira o Fluent Snippets.

### Opção 3: Via plugin MU (Must-Use)

1. Acesse via FTP/SSH: `/wp-content/mu-plugins/`
2. Copie o arquivo `fix-frenet-tiny-integration.php` para esta pasta
3. Pronto! Será carregado automaticamente

---

## 🧪 Como Testar

1. **Faça um pedido teste** no WooCommerce com frete Frenet
2. **Verifique os logs** (se ativou o debug no código):
   - WooCommerce → Status → Logs
   - Procure por: `frenet-tiny-fix`
   - Você verá algo como: "Pedido #123 - method_id alterado de 'frenet' para 'FRENET_FMT_WS_1'"

3. **Verifique no Tiny/Olist**:
   - O pedido deve aparecer com o nome correto da transportadora
   - Ex: "FM Transportes" ao invés de "frenet"

4. **Se não funcionar**:
   - Verifique se o snippet está ativo
   - Verifique os logs do WooCommerce
   - Verifique se o webhook está configurado corretamente no Tiny/Olist

---

## 🔍 Entendendo o instance_id

### O que é o instance_id?

O `instance_id` é um **número único** gerado pelo WooCommerce quando você:
1. Cria uma **Zona de Envio** (ex: "Brasil", "São Paulo", etc)
2. Adiciona o método **Frenet** nessa zona
3. Configura as opções do método

Cada instância do método Frenet em cada zona tem um `instance_id` diferente.

### Como descobrir o instance_id?

#### Método 1: Pela URL (mais fácil)

1. Acesse **WooCommerce** → **Configurações** → **Envio**
2. Clique na **Zona de Envio** onde o Frenet está configurado
3. Clique em **Editar** no método Frenet
4. Olhe a URL do navegador:
   ```
   wp-admin/admin.php?page=wc-settings&tab=shipping&zone_id=1&instance_id=177
   ```
   O número após `instance_id=` é o seu instance_id (neste exemplo: **177**)

#### Método 2: Via banco de dados

Execute esta query no phpMyAdmin ou MySQL:
```sql
SELECT
    instance_id,
    method_id,
    method_order,
    zone_id
FROM
    wp_woocommerce_shipping_zone_methods
WHERE
    method_id = 'frenet';
```

#### Método 3: Via código (debug)

Adicione este código temporariamente no functions.php:
```php
add_action('woocommerce_order_status_changed', function($order_id) {
    $order = wc_get_order($order_id);
    foreach ($order->get_shipping_methods() as $item) {
        error_log('Instance ID: ' . $item->get_instance_id());
        error_log('Method ID: ' . $item->get_method_id());
        error_log('Method Title: ' . $item->get_method_title());
    }
});
```

Depois, faça um pedido teste e verifique o log em `wp-content/debug.log`

### O instance_id é fixo?

✅ **SIM**, cada forma de frete (cada instância do método) tem um instance_id fixo.

**Exemplo prático:**
- Zona "Brasil" → Frenet → instance_id: **177**
- Zona "São Paulo" → Frenet → instance_id: **215**

Se você **não mexer** na configuração da zona de envio, o instance_id permanece o mesmo.

Se você **deletar e recriar** o método, um novo instance_id será gerado.

---

## 🗺️ Mapeamento no Tiny/Olist

### Como mapear as transportadoras?

Segundo a Karine do suporte, você deve mapear usando este formato:

```
FRENET_ID:instance_id
```

**Exemplos:**
- FM Transportes: `FRENET_FMT_WS_1:177`
- Correios PAC: `FRENET_04510:177`
- Correios SEDEX: `FRENET_04014:177`

### Como descobrir os FRENET_ID disponíveis?

#### Método 1: Fazer um pedido teste e ver os logs

1. Ative o **Debug** no plugin Frenet:
   - WooCommerce → Configurações → Envio
   - Edite o método Frenet
   - Marque "Enable logging"

2. Faça uma simulação de frete no checkout
3. Verifique os logs em: WooCommerce → Status → Logs
4. Procure por linhas como:
   ```
   'id' => 'FRENET_FMT_WS_1'
   'label' => 'FM Transportes - Econômico'
   ```

#### Método 2: Via banco de dados (após um pedido)

```sql
SELECT
    om.order_item_id,
    om.order_item_name,
    ometa.meta_key,
    ometa.meta_value
FROM
    wp_woocommerce_order_items om
    LEFT JOIN wp_woocommerce_order_itemmeta ometa ON om.order_item_id = ometa.order_item_id
WHERE
    om.order_item_type = 'shipping'
    AND ometa.meta_key = 'FRENET_ID';
```

#### Método 3: Verificar na API da Frenet

Os códigos mais comuns são:
- **Correios PAC**: FRENET_04510
- **Correios SEDEX**: FRENET_04014
- **FM Transportes - Econômico**: FRENET_FMT_WS_1
- **FM Transportes - Expresso**: FRENET_FMT_WS_2
- **Jadlog**: FRENET_JADLOG_*
- **Azul Cargo**: FRENET_AZUL_*

Mas **cada conta Frenet pode ter transportadoras diferentes**! Por isso é importante verificar nos logs.

---

## 🐛 Troubleshooting

### O nome ainda aparece como "frenet" no Tiny

**Possíveis causas:**

1. **O snippet não está ativo**
   - Verifique se está ativado no Fluent Snippets
   - Ou se está no functions.php sem erros

2. **Cache do webhook**
   - Force o reenvio do webhook no WooCommerce
   - Ou crie um novo pedido teste

3. **Mapeamento não configurado no Tiny**
   - Acesse Tiny → Integrações → WooCommerce → Formas de Envio
   - Certifique-se de que mapeou: `FRENET_FMT_WS_1:177` → "FM Transportes"

4. **O pedido foi criado antes de ativar o fix**
   - O fix só funciona para pedidos **novos** ou **reenviados** após ativar
   - Reenvie o webhook manualmente no WooCommerce

### Como reenviar um webhook manualmente?

1. Acesse **WooCommerce** → **Configurações** → **Avançado** → **Webhooks**
2. Clique no webhook do Tiny/Olist
3. Role até "Logs"
4. Clique em **Reenviar** em um pedido específico

### Verificar se o payload está sendo modificado

Adicione este código **temporariamente** no functions.php:

```php
add_filter('woocommerce_webhook_payload', function($payload) {
    error_log('WEBHOOK PAYLOAD: ' . print_r($payload, true));
    return $payload;
}, 999, 1);
```

Depois, faça um pedido teste e verifique o arquivo `wp-content/debug.log`

---

## 📝 Notas Técnicas

### Hooks utilizados

1. **`woocommerce_webhook_payload`**
   - Intercepta o payload antes de enviar via webhook
   - Prioridade: 10
   - Usado para: Modificar o method_id para o FRENET_ID

2. **`woocommerce_rest_prepare_shop_order_object`**
   - Intercepta a resposta da REST API
   - Prioridade: 10
   - Usado para: Manter consistência na API REST

### Compatibilidade

- ✅ WooCommerce 3.0+
- ✅ WooCommerce 8.0+
- ✅ WooCommerce 10.0+
- ✅ WordPress 5.0+
- ✅ Plugin Frenet 2.x

### Performance

- ⚡ Overhead mínimo: apenas processa quando há shipping_lines no payload
- 🔒 Seguro: não modifica o banco de dados, apenas o payload do webhook
- 📊 Adiciona log opcional para debug (pode desativar removendo o bloco de log)

---

## 📞 Suporte

Se tiver problemas:

1. Verifique os logs do WooCommerce
2. Verifique o debug.log do WordPress
3. Teste com um pedido novo
4. Verifique se o mapeamento no Tiny está correto

---

## 📜 Licença

Este código é fornecido como está, sem garantias. Sinta-se livre para modificar conforme necessário.

---

**Última atualização**: 06/01/2026
**Versão**: 1.0.0
