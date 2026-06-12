# Guide lomi. pour Magento 2

Guide marchand en français pour installer, configurer et mettre en production le module **lomi.** sur Magento 2.

## Vue d’ensemble

Le module permet à vos clients de payer via le **checkout hébergé lomi.** :

1. Le client choisit **lomi.** au checkout Magento et valide la commande.
2. Magento crée une session checkout via l’API lomi.
3. Le client est redirigé vers `checkout.lomi.africa` pour payer.
4. lomi. confirme le paiement via **webhook** et/ou **URL de retour**.
5. La commande passe en **Processing** (En cours de traitement).

## Prérequis

- Magento **2.4.x**
- Devise boutique : **XOF**, **USD** ou **EUR**
- **HTTPS** en production (obligatoire pour les webhooks)
- Compte lomi. : [dashboard.lomi.africa](https://dashboard.lomi.africa)

## Installation

### Via Composer

```bash
composer require lomi/magento2-payments
php bin/magento module:enable Lomi_Payments
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy fr_FR en_US
php bin/magento cache:flush
```

### Installation manuelle

Copiez le module dans `app/code/Lomi/Payments/`, puis exécutez les commandes ci-dessus.

## Configuration admin

**Magasins → Configuration → Ventes → Moyens de paiement → lomi.**

| Champ | Description |
|-------|-------------|
| **Enabled** | Activer lomi. au checkout |
| **Test Mode** | **Oui** = sandbox (tests) ; **Non** = production |
| **Test API secret key** | Clé `lomi_sk_test_…` (dashboard, mode test) |
| **Test webhook signing secret** | Secret `whsec_…` du webhook **test** |
| **Live API secret key** | Clé `lomi_sk_live_…` (production) |
| **Live webhook signing secret** | Secret `whsec_…` du webhook **live** |

### Configurer le webhook

1. Copiez l’**URL webhook** affichée dans l’admin Magento (ex. `https://votre-boutique.com/lomi/payment/webhook`).
2. Dashboard lomi. → **Developers → Webhooks** → créer un endpoint :
   - URL : celle ci-dessus
   - Événements : **`PAYMENT_SUCCEEDED`** (minimum)
   - Mode : **test** ou **live** selon **Test Mode** dans Magento
3. Copiez le **signing secret** (`whsec_…`) dans le champ correspondant dans Magento.

**Attention :** le signing secret n’est **pas** la clé API `sk_…`. Chaque endpoint webhook a son propre `whsec_…`.

### Sauvegarder les secrets

Les champs secrets affichent des points après enregistrement. Pour modifier :

- **Recollez toujours la valeur complète** avant de cliquer *Enregistrer la configuration*, ou
- Utilisez la CLI :

```bash
php bin/magento config:set payment/lomi/test_webhook_secret "whsec_VOTRE_SECRET"
php bin/magento cache:flush
```

## Tester en sandbox

1. **Test Mode** = Oui
2. Clés test + webhook test configurés
3. Pour le dev local : [ngrok](https://ngrok.com/) + voir [dev/README.md](../dev/README.md)
4. Carte test réussie : **`4242 4242 4242 4242`** (date future, CVC au choix)

### Résultats attendus

| Test | Résultat |
|------|----------|
| Bouton « Test webhook » dashboard | **200** + réponse `ignored` (normal) |
| Vrai paiement test | Webhook **PAYMENT_SUCCEEDED** → **200** ; commande **Processing** |

Autres cartes test : [Sandbox payments](https://docs.lomi.africa/start/sandbox-payments).

## Mise en production

Checklist avant de désactiver le mode test :

- [ ] Boutique en **HTTPS** avec URL de base correcte (Magasins → Configuration → Général → URL du site web)
- [ ] **Test Mode** = **Non**
- [ ] Clés **live** + webhook **live** configurés
- [ ] Webhook live dans le dashboard avec l’URL production
- [ ] Mode Magento **Production** : `setup:di:compile`, déploiement static, cache
- [ ] Un paiement réel (montant faible) validé de bout en bout

## Dépannage

| Problème | Cause probable | Action |
|----------|----------------|--------|
| Webhook **401** `auth failed` | Mauvais `whsec_…` ou mélange test/live | Recopier le secret du bon webhook (même mode que Test Mode) |
| Webhook **200** `ignored` sur test dashboard | Normal pour `TEST_WEBHOOK` | Faire un vrai paiement test pour `PAYMENT_SUCCEEDED` |
| Commande reste **Pending** | Webhook absent ou session pas `completed` | Vérifier logs webhook dashboard + logs Magento `var/log/system.log` |
| Paiement OK sur checkout lomi. mais pas Magento | Pas de webhook ; pas de retour callback | Le webhook est le chemin fiable — le corriger en priorité |

Logs Magento :

```bash
grep -i lomi var/log/system.log | tail -20
```

## Devises

| Devise | Montant envoyé à l’API |
|--------|------------------------|
| XOF | Francs entiers (505 F → `505`) |
| USD / EUR | Centimes (10,50 € → `1050`) |

## Limites connues

- Remboursements : via le dashboard lomi., pas depuis Magento admin
- Pas de paiement par carte enregistrée via ce module (checkout hébergé uniquement)
- Devises hors XOF / USD / EUR : méthode masquée au checkout

## Développement local

Environnement Docker : [dev/README.md](../dev/README.md).

Documentation API lomi. : [docs.lomi.africa](https://docs.lomi.africa).

## Support

- Dashboard : [dashboard.lomi.africa](https://dashboard.lomi.africa)
- Site : [lomi.africa](https://lomi.africa)
