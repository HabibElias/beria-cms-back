# Public catalogue, member accounts and loan requests

The existing management APIs now require the **admin** role. Regular members and existing librarian-role accounts use the reader experience. This is enforced in Laravel middleware as well as frontend route guards; hiding menu items is not the authorization boundary.

## Routes and data

| Access | Endpoint | Purpose |
| --- | --- | --- |
| Public | `GET /api/catalogue/books` | Paginated public books; search `q`, category and availability filters; at most 48 books per page |
| Public | `GET /api/catalogue/books/{id}` | Book details without internal notes, shelf, condition or storage paths |
| Public | `GET /api/catalogue/categories` | Categories with book counts |
| Public | `POST /api/register` | Name, email, phone, password and password confirmation; returns a member and JWT |
| Public | `POST /api/login` | Sign in for any account role |
| Authenticated | `GET /api/my-loans` | Only the authenticated reader's current checkouts |
| Authenticated | `GET/POST /api/loan-requests` | Read own request history / submit a book ID and optional note |
| Owner | `DELETE /api/loan-requests/{id}` | Cancel a pending request; another reader's request returns 404 |
| Admin | `GET /api/admin/loan-requests` | Review queue with status and book/reader search |
| Admin | `POST /api/admin/loan-requests/{id}/approve` | Issue a real checkout; optional return date and reader-visible note |
| Admin | `POST /api/admin/loan-requests/{id}/decline` | Decline with an optional reader-visible note |
| Admin | Existing `/api/books`, `/members`, `/categories`, `/checkouts`, `/analytics` | The existing management APIs |

Registration requires a confirmed password of 12–72 characters within the password hasher's byte limit. Role assignment is prohibited; the server always creates `role=user`. Passwords use the User model's hash cast. Sign-up and sign-in are rate limited. Admin-created accounts also require an explicit valid password, rather than receiving a shared default. Existing account passwords are not reset. Own-profile updates accept only name and email and cannot change the role.

Email addresses are normalized on account creation and editing. Case-insensitive uniqueness checks include legacy mixed-case accounts so changing an email cannot shadow another reader or administrator at sign-in.

## Request lifecycle

Submitting a request does not reserve a book or create a checkout. A reader cannot duplicate a pending request, request an unavailable copy, or submit a new request when current loans plus pending requests already total three. A nullable unique pending key backs the duplicate constraint.

Only a pending request can be cancelled, approved or declined. Approval checks the reader's current three-loan limit and atomically claims an available copy, creates a checkout, records the reviewing admin and clears the pending key. If a competing approval or manual checkout already claimed the copy, the operation fails without a partial loan or request decision. An omitted approval return date defaults to two weeks from approval; a supplied date must be more than one week away.

Declining or cancellation creates no loan. Notes from the admin are visible to the requesting reader. A return records the return, clears the request's checkout link and restores availability in one transaction. Approved requests without a current checkout display as returned; the reader may request that book again. Manual admin checkouts use the same transactional borrowing rules as request approval.

## Deployment

Deploy `HabibElias/beria-cms-back` first with `RUN_MIGRATIONS=true`. The additive migration creates `loan_requests` and its indexes/foreign keys; existing books, users and checkouts are retained. No database reset or reseed is needed for this feature. Keep existing app/JWT keys and Turso credentials. Then deploy `HabibElias/beria-cms/front-end-r` with `VITE_APP_BASE_URL=https://beria-cms-back.onrender.com/api`.

The frontend's public catalogue is at `/books` and `/`, reader activity at `/my-loans`, own settings at `/account`, and the existing management app at `/admin`. Admins sign in through `/login` and land in their workspace; other roles land in their loans and requests. Safe local book destinations survive sign-up/sign-in. Existing management links redirect to the corresponding admin route. Public login no longer displays administrator demo credentials.

Email verification, email delivery and password-reset emails are not implemented; the UI does not promise them. Loan status refreshes periodically while the reader or admin page is open and on return to the tab.

## Verification

`MemberLibraryTest` exercises public fields, registration/password rules, role escalation attempts, protected management mutations, owner-only request access, duplicate and unavailable requests, cancellation, competing approvals, borrowing limits, returns, re-requests and rollback after a failed checkout insert. It runs on the native SQLite/libSQL adapter used by Turso. The frontend browser integration uses a separate disposable SQLite database and real Laravel HTTP responses, with separate reader/admin sessions; it does not create production test accounts or loans. Remote Render/Turso verification still requires live service access.
