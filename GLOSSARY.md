# KULIK SYSTEM Event Landing

Private Grand Opening invitation page for November 5, 2026. One job: collect Invitation Requests from Guests.

## Language

**Guest**:
A person who opens the landing page and may request an invitation to the event.
_Avoid_: Visitor, user, lead, registrant

**Invitation Request**:
A Guest's submitted request to attend the event: name, email, profession, optional company, plus tracking fields.
_Avoid_: Lead, RSVP, registration, signup, form submission

**Delivery**:
An attempt to send one Invitation Request to a channel (email, CRM, or Telegram). Each channel has its own status: `pending`, `sent`, or `failed`.
_Avoid_: Notification, sync, webhook call

**Storefront**:
The public KULIK SYSTEM shop at kuliksystem.us. The event page borrows its type, color, and controls.
_Avoid_: Prod site, main site, the website
