---
title: "Free and commercial use"
sidebarTitle: "Overview"
description: "Understand when FLEX Fields is free to use and when hosted products or software distribution require a commercial license."
icon: "scroll-text"
---

<Badge color="purple" shape="pill">Source-available</Badge> <Badge color="gray" shape="pill">License v2.0 · 1 October 2026</Badge>

FLEX Fields is available to inspect and download through its official public channels. Qualifying personal, internal and bespoke client use is free. Commercial hosted products and software distribution require the appropriate grant.

**Free use is limited by permitted use, not by disabled components.** The standard commercial plans also provide the same package features. Choose a license by who owns and operates the application and what your customers receive.

<CardGroup cols={2}>
  <Card title="Plans and pricing" icon="wallet" href="/docs/plans">
    Compare one product, one company, a corporate group and Custom distribution rights.
  </Card>
  <Card title="Licensing questions" icon="circle-question-mark" href="/docs/licensing-faq">
    Find examples for agencies, SaaS applications, customer installations and existing purchases.
  </Card>
</CardGroup>

## What can remain free?

Every condition of the [free-use grant](/docs/license-terms#section-1-definitions) must hold. These are common eligible situations:

<AccordionGroup>
  <Accordion title="Personal, educational and research projects" icon="graduation-cap">
    Use the package within the license's personal, educational or research scope. Operating a commercial software product does not become free merely because it began as a learning project.
  </Accordion>
  <Accordion title="Your own internal operations" icon="building-2">
    A staff CRM, inventory system or other internal tool for one entity can qualify. Ordinary company sites, shops and incidental customer portals can also qualify when they support your own goods or services and the application itself is not the software product being supplied.

    A staff-only screen that forms or configures the software service sold to customers does not become free simply because customers cannot log into that screen.
  </Accordion>
  <Accordion title="Qualifying bespoke client work" icon="handshake">
    A bespoke application for an identified client's internal operations can qualify when all work-for-hire conditions are met: you remain the responsible licensee/operator, the client receives runtime access only, and the client does not sell the application as a software product or service.

    A client can instead obtain the official package under its own eligible free or commercial grant and appoint your agency as its contractor for a genuine bespoke project. See [agency and client scenarios](/docs/licensing-faq#agencies-and-client-work).
  </Accordion>
  <Accordion title="Private evaluation before a commercial launch" icon="flask-conical">
    Private development and testing of a prospective commercial product can qualify before live customer operations, a paid pilot or production launch. Obtain the appropriate commercial grant before that activity begins.
  </Accordion>
</AccordionGroup>

There is no revenue threshold. A profitable company's eligible internal application can remain free; a small commercial SaaS needs the appropriate grant. A live freemium tier belongs to the commercial product.

## Internal panel and commercial product in the same application

Assess **the functionality implemented using FLEX**, in the context of the service being supplied. Sharing a Laravel application, database or backend with a commercial product does not by itself make a separate internal FLEX use commercial. A separate Filament panel or staff-only login does not by itself make product functionality free.

The examples below concern a company operating a commercial hosted software product. Other free-use conditions still apply.

| Use of FLEX | Scope | Why |
|---|---|---|
| Staff inspect processing statuses, failed jobs and error logs for operational monitoring; FLEX does not implement or configure the customer service | Free internal use | This is internal monitoring, even in the same backend |
| Employee records, internal accounting or company administration | Free internal use | FLEX serves the company's own business administration |
| Staff inspect existing records for internal monitoring or troubleshooting, without implementing or configuring consumer-product features through FLEX | Free internal use | Customer-related data alone does not make an internal dashboard a product feature |
| Customers use FLEX fields, uploads or charts as features of the commercial application | Commercial use | FLEX implements functionality supplied with the product |
| Staff use FLEX controls to define comparison rules, calculation parameters or service features used by the consumer product | Commercial use | Staff-only access does not exempt product configuration |
| FLEX renders an analysis or report supplied to customers as a feature of the commercial software service | Commercial use | FLEX implements a product output, rather than only displaying operational information internally |

**Read-only versus editable is not the dividing line.** A read-only FLEX chart supplied as a customer feature of the commercial product is commercial use. An editable employee-directory form can be free internal use.

In the [license guide](/docs/request-license), select the actual use of FLEX. Do not select commercial hosted use solely because another part of the same application is a commercial product. Select commercial hosted use when FLEX itself implements or configures that product's functionality, including staff-only product controls. If FLEX has several intended uses, assess each; a qualifying internal use does not exempt a separate commercial use.

## Choose the right commercial scope

<Steps>
  <Step title="Check how the application is delivered">
    Customers receiving only interface or API access to your hosted application fit the hosted-use model. Customer installations, server source, containers, reusable kits and OEM or reseller rights require expressly agreed Custom scope, outside the genuine bespoke direct-client exception.
  </Step>
  <Step title="Identify the product owner and operator">
    One named product can use Single Product. One entity's current and future products can use Unlimited Company. A named parent and its qualifying wholly owned subsidiaries can use Enterprise Group.
  </Step>
  <Step title="Buy the matching plan">
    [Buy a standard license](https://shop.bjanczak.com/checkout/buy/9035f5b4-c5a2-4b03-867d-109b48d49134) directly, selecting the matching plan in checkout. No enquiry is required first. For Custom / OEM, unclear scope, upgrades or transfers, contact [barek122@gmail.com](mailto:barek122@gmail.com) before payment. Keep the authenticated License Confirmation issued after confirmed payment; a checkout receipt alone is not the grant.
  </Step>
</Steps>

## Shared permissions and limits

| Within the applicable grant | Separate rights are needed |
|---|---|
| Private integration, styling and modifications | Independent public distributions, mirrors and reusable package downloads outside separately granted platform permissions |
| Authorized employees, contractors and hosting providers working for the licensee | Independent use by another company or client |
| Normal browser assets and ordinary data or document exports | Server-side copies, customer installations, source handover and OEM distribution outside permitted exceptions |
| Your own application brand, while retaining required notices | Removing copyright or third-party notices, or claiming the package as your own |

Standard plans are one-time purchases with perpetual rights within their scope. They include subsequent official updates, including major versions of this package, under the issued terms. They do not promise future releases, support SLAs, custom work or licenses for other packages such as FLEX Forms.

You can view and fork the official public repository within GitHub's own platform
permissions. This does not grant unrestricted commercial use, customer
installations, OEM rights or independent distribution outside those permissions.

## Full terms and existing grants

This overview explains the current licensing model. The [full license](/docs/license-terms) contains the grants and conditions; your accepted License Confirmation identifies the purchased scope. Expressly agreed Custom terms can vary that scope.

The 17 October 2026 clarification acknowledges GitHub platform permissions. Prices,
commercial scope and previously issued grants are unchanged.

Previously issued licenses retain their agreed terms. A new plan or license version does not silently expand or reduce an existing commercial grant. See [existing purchases and upgrades](/docs/licensing-faq#existing-purchases-and-upgrades).
