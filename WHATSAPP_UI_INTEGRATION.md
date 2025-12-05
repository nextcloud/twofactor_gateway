# Integração da UI do WhatsApp Cloud API

## Arquivos Criados

### Frontend (Vue.js 3 + Typescript)

**`src/views/WhatsAppCloudApiSettings.vue`** - Componente de configuração admin

Campos implementados conforme solicitado:
- ✅ **Número de Telefone ID** - Phone Number ID from Meta Business Account
- ✅ **ID da Conta WhatsApp Business** - Business Account ID
- ✅ **Chave da API** - API Access Token
- ✅ **Endpoint da API** - Optional, defaults to https://graph.facebook.com

Features:
- Interface responsiva com Nextcloud Vue Components
- Validação de campos obrigatórios
- Botão "Save Configuration" (salva quando formulário é válido)
- Botão "Test Connection" (testa credenciais)
- Botão "Cancel" (revert changes)
- Instruções passo-a-passo para obter credenciais
- Mensagens de status (sucesso/erro)
- Campo de API Key com visibilidade toggleável
- Loading indicators durante operações

### Backend (PHP)

**`lib/Controller/WhatsAppCloudApiConfigurationController.php`** - API REST para configuração

Endpoints:
- `GET /apps/twofactor_gateway/api/v1/whatsapp/configuration` - Obter configuração atual
- `POST /apps/twofactor_gateway/api/v1/whatsapp/configuration` - Salvar configuração
- `POST /apps/twofactor_gateway/api/v1/whatsapp/test` - Testar conexão

## Como Integrar na Aplicação

### 1. Registrar Rotas (em `appinfo/routes.php` ou equivalente)

```php
return [
    'routes' => [
        // Existing routes...
        
        // WhatsApp Cloud API Configuration
        [
            'name' => 'WhatsAppCloudApiConfiguration#getConfiguration',
            'url' => '/api/v1/whatsapp/configuration',
            'verb' => 'GET',
        ],
        [
            'name' => 'WhatsAppCloudApiConfiguration#saveConfiguration',
            'url' => '/api/v1/whatsapp/configuration',
            'verb' => 'POST',
        ],
        [
            'name' => 'WhatsAppCloudApiConfiguration#testConfiguration',
            'url' => '/api/v1/whatsapp/test',
            'verb' => 'POST',
        ],
    ],
];
```

### 2. Adicionar Rota no Admin Settings (em `src/main.ts` ou routing)

```typescript
import WhatsAppCloudApiSettings from './views/WhatsAppCloudApiSettings.vue'

const router = createRouter({
    history: createWebHistory(),
    routes: [
        // ... existing routes
        {
            path: '/admin/whatsapp-cloud-api',
            name: 'whatsapp-cloud-api-settings',
            component: WhatsAppCloudApiSettings,
            meta: {
                requiresAdmin: true,
            },
        },
    ],
})
```

### 3. Adicionar Link no Menu de Admin

Em templates ou componentes de navegação admin:

```vue
<li>
    <router-link to="/admin/whatsapp-cloud-api">
        {{ t('twofactor_gateway', 'WhatsApp Cloud API') }}
    </router-link>
</li>
```

### 4. Melhorar Segurança do Controller

Atualmente, o `isAdmin()` é básico. Substituir por:

```php
private function isAdmin(): bool {
    return \OC::$server->getUserSession()->isLoggedIn() && 
           \OC::$server->getGroupManager()->isAdmin($this->userId);
}
```

Ou usar decoradores Nextcloud (se disponível na versão):
```php
/**
 * @AdminRequired
 */
public function saveConfiguration(...): DataResponse
```

## Interface Visual

A UI segue o padrão Nextcloud com:

- **Layout responsivo** - Funciona em desktop e mobile
- **Tema escuro/claro** - Suporta ambos os temas do Nextcloud
- **Componentes nativos** - Usa `@nextcloud/vue` components
- **Acessibilidade** - Labels, help text, campo disabled quando necessário
- **Feedback de usuário** - Toast notifications, loading states

### Campos do Formulário

1. **Phone Number ID**
   - Campo de texto obrigatório
   - Placeholder: "e.g., 1234567890"
   - Descrição: The ID of your WhatsApp phone number from Meta Business Account

2. **WhatsApp Business Account ID**
   - Campo de texto obrigatório
   - Placeholder: "e.g., 1234567890"
   - Descrição: The ID of your WhatsApp Business Account from Meta Business Manager

3. **API Access Token**
   - Campo password (com toggle de visibilidade)
   - Obrigatório
   - Placeholder: "Paste your API token here"
   - Descrição: Your Meta Graph API token with whatsapp_business_messaging permission

4. **API Endpoint** (Opcional)
   - Campo de texto
   - Placeholder: "https://graph.facebook.com"
   - Descrição: Default: https://graph.facebook.com. Change only if using a custom endpoint.

### Botões

1. **Save Configuration**
   - Cor: Primary (azul)
   - Desabilitado quando: formulário inválido ou sem mudanças
   - Loading: Mostra spinner enquanto salva
   - Ação: Salva configuração e mostra sucesso/erro

2. **Test Connection**
   - Cor: Tertiary
   - Visível apenas quando: configuração já existe
   - Desabilitado quando: saving
   - Ação: Testa conexão com as credenciais

3. **Cancel**
   - Cor: Secondary (cinza)
   - Visível apenas quando: formulário foi modificado
   - Ação: Revert para valores originais

## Instruções Exibidas

A UI mostra guia passo-a-passo quando nenhuma configuração existe:

1. Go to Meta Business Manager
2. Navigate to WhatsApp Manager → Phone Numbers
3. Copy your Phone Number ID
4. Go to Settings → Business Account → Copy your Account ID
5. Create a Graph API token in your app settings
6. Paste all credentials above and save

Com links para:
- Meta Business Manager: https://business.facebook.com
- Documentação: https://developers.facebook.com/docs/whatsapp/cloud-api/get-started/

## Tradução (i18n)

Todos os textos usam `t('twofactor_gateway', '...')` para suportar múltiplos idiomas.

Adicionar em `lib/Command/Configure.php` ou arquivo de tradução correspondente:

```php
'Phone Number ID' => 'Número de Telefone ID',
'WhatsApp Business Account ID' => 'ID da Conta WhatsApp Business',
'API Access Token' => 'Token de Acesso da API',
'API Endpoint' => 'Endpoint da API',
// ... etc
```

## Testing

### Testar a UI

```bash
# Build frontend
npm run build

# Serve local
npm run dev

# Acessar em:
# http://localhost:8080/admin/whatsapp-cloud-api
```

### Testar Endpoints

```bash
# Get configuration
curl -X GET \
  http://localhost/ocs/v2.php/apps/twofactor_gateway/api/v1/whatsapp/configuration \
  -H "OCS-APIRequest: true"

# Save configuration
curl -X POST \
  http://localhost/ocs/v2.php/apps/twofactor_gateway/api/v1/whatsapp/configuration \
  -H "OCS-APIRequest: true" \
  -d "phone_number_id=123&business_account_id=456&api_key=token&api_endpoint=https://graph.facebook.com"

# Test connection
curl -X POST \
  http://localhost/ocs/v2.php/apps/twofactor_gateway/api/v1/whatsapp/test \
  -H "OCS-APIRequest: true" \
  -d "phone_number_id=123&business_account_id=456&api_key=token"
```

## Segurança

### Medidas Implementadas

✅ API Key nunca é retornada ao frontend (GET retorna string vazio)
✅ Validação de campos obrigatórios no backend
✅ Acesso restrito a administradores
✅ CSRF protection via Nextcloud
✅ HTTPS obrigatório para Meta API
✅ Logging de ações de configuração

### Recomendações

- Implementar proper admin check (decorador @AdminRequired)
- Adicionar rate limiting nos endpoints
- Encriptar credentials no banco de dados
- Log audit trail de mudanças de configuração
- Validar e sanitizar inputs

## Próximos Passos

1. ✅ Criar componente Vue (`WhatsAppCloudApiSettings.vue`)
2. ✅ Criar controlador API (`WhatsAppCloudApiConfigurationController.php`)
3. Registrar rotas na aplicação
4. Integrar componente no layout admin
5. Adicionar testes e1e
6. Melhorar segurança conforme recomendações

---

**Status**: ✅ Pronto para integração
**Componentes**: Vue 3 + Composition API
**Compatibilidade**: Nextcloud 28+, PHP 8.2+
