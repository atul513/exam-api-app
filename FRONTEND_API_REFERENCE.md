# Frontend API Reference
**Base URL:** `http://127.0.0.1:8000/api`  
**Auth:** All protected endpoints require `Authorization: Bearer {token}` header.

---

## Legend
- 🔓 Public — no token needed
- 🔐 Auth — valid token required (any role)
- 👑 Admin/SuperAdmin only
- 🎓 Teacher/Admin/SuperAdmin
- 🧑‍🎓 Student only
- 👨‍👩‍👧 Parent only
- 🔑 SuperAdmin only

---

## 1. Authentication

### Register
```
🔓 POST /register
Body: { name, email, password, password_confirmation, role? }
Returns: { success, token, user }
```

### Login
```
🔓 POST /login
Body: { email, password }
Returns: { success, token, user }
```

### Logout
```
🔐 POST /logout
Returns: { success, message }
```

### Get Logged-in User
```
🔐 GET /user
Returns: { success, user }
```

---

## 2. Google OAuth

### Option A — Code Flow (recommended for Vue SPA)
```
🔓 GET  /auth/google/redirect
Returns: { url }  → redirect user to this URL

🔓 POST /auth/google/callback
Body: { code }    → Google sends this to your frontend redirect URI
Returns: { success, token, user }
```

### Option B — Token Flow (if using Google JS SDK)
```
🔓 POST /auth/google/token
Body: { access_token }
Returns: { success, token, user }
```

**Vue flow:**
1. Call `GET /auth/google/redirect` → get `url`
2. Redirect user to `url`
3. Google redirects to `GOOGLE_REDIRECT_URI` with `?code=...`
4. POST code to `/auth/google/callback` → get Sanctum token

---

## 3. Profile

All roles use the same endpoints. The `stats` block in the response adapts per role.

### Get Profile
```
🔐 GET /profile
Returns:
{
  data: {
    id, name, first_name, last_name, username, email,
    phone_code, phone, avatar_url, country, address, city,
    postal_code, role, member_since,
    subscription: { plan_name, billing_cycle, expires_at, days_remaining, is_active } | null,
    stats: {
      // student:  { quiz: {...}, practice: {...}, recent_attempts: [...] }
      // teacher:  { quizzes, questions, practice_sets, student_attempts }
      // admin:    { users, content, attempts }
      // parent:   { children_count, children: [...] }
    }
  }
}
```

### Update Profile
```
🔐 PUT /profile
Body: { first_name?, last_name?, username?, email?, phone_code?, phone?,
        country?, address?, city?, postal_code? }
Returns: { message, data }
```

### Upload Avatar
```
🔐 POST /profile/avatar
Body: FormData { avatar: File (jpg/png/webp, max 2MB) }
Returns: { message, avatar_url }
```

### Remove Avatar
```
🔐 DELETE /profile/avatar
Returns: { message }
```

---

## 4. Home Page (Public)

### Combined — single request for both sections
```
🔓 GET /home?limit=6
Returns: { data: { practice_sets: [...], exams: [...] } }
```

### Practice Sets section
```
🔓 GET /home/practice-sets?limit=8
Returns: { data: [...] }
```

### Exams section
```
🔓 GET /home/exams?limit=8
Returns: { data: [...] }
```

---

## 5. Plans & Subscriptions

### List Plans (public, with user subscription status if logged in)
```
🔓 GET /plans
🔐 GET /plans          (returns user_subscription per plan when token provided)
Returns: { data: [{ id, name, price, billing_cycle, features, user_subscription }] }
```

### Show Single Plan
```
🔓 GET /plans/{id}
Returns: { data: { ...plan, user_subscription } }
```

### Subscribe to a Plan
```
🔐 POST /plans/{id}/subscribe
Body (free plan): {}
Body (paid plan): { payment_method: "upi|bank_transfer|card|cash|other", payment_reference: "TXN123" }
Returns: { message, data: { status, expires_at, is_active, plan } }
Note: Free → status=active immediately. Paid → status=pending until admin activates.
```

### My Current Subscription
```
🔐 GET /my/subscription
Returns: { data: { status, expires_at, is_active, days_remaining, plan } }
```

### My Subscription History
```
🔐 GET /my/subscriptions
Returns: paginated list
```

### Cancel Subscription
```
🔐 POST /my/subscription/cancel
Returns: { message }
```

---

## 6. Dashboards (role-specific)

```
🧑‍🎓 GET /student/dashboard
🎓   GET /teacher/dashboard
🎓   GET /teacher/students?search=&per_page=15
👑   GET /admin/dashboard
👑   GET /admin/users?search=&role=&per_page=15
👑   POST /admin/users   { name, email, password, password_confirmation, role }
🔑   GET /superadmin/dashboard
🔑   GET /superadmin/users
🔑   PATCH /superadmin/users/{id}/role   { role }
👨‍👩‍👧  GET /parent/dashboard
```

---

## 7. Quizzes & Exams

### CRUD (Admin/Teacher)
```
🎓 GET    /v1/quizzes?type=exam&status=published
🎓 POST   /v1/quizzes
🎓 GET    /v1/quizzes/{id}
🎓 PUT    /v1/quizzes/{id}
🎓 DELETE /v1/quizzes/{id}
🎓 POST   /v1/quizzes/{id}/publish
🎓 POST   /v1/quizzes/{id}/archive
🎓 POST   /v1/quizzes/{id}/duplicate
```

### Quiz Questions & Schedules
```
🎓 GET  /v1/quizzes/{id}/questions
🎓 POST /v1/quizzes/{id}/questions/sync   { questions: [{question_id, sort_order, marks_override}] }
🎓 GET  /v1/quizzes/{id}/schedules
🎓 POST /v1/quizzes/{id}/schedules/sync
```

### Attempt Flow (Student)
```
🔐 GET  /v1/quizzes/{id}/check-access
     Returns: { allowed, reason, resume_attempt_id? }

🔐 POST /v1/quizzes/{id}/start
     Returns: { attempt_id, questions: [...] }

🔐 GET  /v1/attempts/{id}
🔐 POST /v1/attempts/{id}/answer   { question_id, selected_option_ids?, text_answer?, ... }
🔐 POST /v1/attempts/{id}/submit
🔐 GET  /v1/attempts/{id}/result
🔐 GET  /v1/attempts/{id}/report
🔐 GET  /v1/attempts/{id}/report/pdf
```

### My Attempts (Student dashboard)
```
🔐 GET /v1/my/attempts
🔐 GET /v1/my/quizzes
```

### Leaderboard
```
🔐 GET /v1/quizzes/{id}/leaderboard
```

### Quiz Categories
```
🔐 GET    /v1/quiz-categories
🔐 POST   /v1/quiz-categories
🔐 GET    /v1/quiz-categories/{id}
🔐 PUT    /v1/quiz-categories/{id}
🔐 DELETE /v1/quiz-categories/{id}
```

---

## 8. Practice Sets

### CRUD (Admin/Teacher)
```
🔐 GET    /v1/practice-sets
🔐 POST   /v1/practice-sets
🔐 GET    /v1/practice-sets/{id}
🔐 PUT    /v1/practice-sets/{id}
🔐 DELETE /v1/practice-sets/{id}
🔐 POST   /v1/practice-sets/{id}/publish
🔐 GET    /v1/practice-sets/{id}/questions
```

### Practice Flow (Student)
```
🔐 GET  /v1/practice-sets/{id}/start
     Returns: { practice_set, questions, progress, summary }

🔐 POST /v1/practice-sets/{id}/check-answer
     Body: { question_id, selected_option_ids?, text_answer?, fill_blank_answers?, match_pairs_answer? }
     Returns: instant grading result

🔐 GET /v1/practice-sets/{id}/progress
     Returns: { summary, details }
```

### Reward Points
```
🔐 GET /v1/my/reward-points
Returns: { total_points, history }
```

---

## 9. Exam Sections (Flexible Taxonomy)

Hierarchical taxonomy: Exam Group → Exam → Variant → Subject → Chapter → Topic

### Public / Lookup
```
🔐 GET /v1/exam-sections/types
     Returns all available type labels (for admin dropdowns)

🔐 GET /v1/exam-sections?format=tree
     Returns full nested tree

🔐 GET /v1/exam-sections?type=exam&parent_id=1&is_active=1&search=jee
     Returns paginated flat list with filters

🔐 GET /v1/exam-sections/{id}
     Returns section + children + breadcrumb

🔐 GET /v1/exam-sections/{id}/tree
     Returns full subtree from this node

🔐 GET /v1/exam-sections/{id}/breadcrumb
     Returns: [{ id, name, type, slug }, ...]   e.g. Competitive > JEE > Physics

🔐 GET /v1/exam-sections/{id}/content?status=published
     Returns all quizzes + practice_sets under this node and all descendants
```

### Admin CRUD
```
🔐 POST   /v1/exam-sections
     Body: { name, type, parent_id?, code?, description?, short_name?,
             icon_url?, image_url?, meta?, sort_order?, is_active?, is_featured? }

🔐 PUT    /v1/exam-sections/{id}
🔐 DELETE /v1/exam-sections/{id}   (blocked if has children)

🔐 POST /v1/exam-sections/bulk-create
     Body: { sections: [{ name, type, parent_id?, code?, meta?, sort_order? }] }
     Note: parent_id can reference a 1-based index from same batch for ordering
```

### Linking Content to Sections
```
🔐 POST   /v1/exam-sections/{id}/link
     Body: { linkable_type: "quiz|practice_set", linkable_id: 5 }

🔐 DELETE /v1/exam-sections/{id}/unlink
     Body: { linkable_type: "quiz|practice_set", linkable_id: 5 }
```

---

## 10. Questions

```
🔐 GET    /v1/questions
🔐 POST   /v1/questions
🔐 GET    /v1/questions/{id}
🔐 PUT    /v1/questions/{id}
🔐 DELETE /v1/questions/{id}
🔐 POST   /v1/questions/{id}/clone
🔐 POST   /v1/questions/{id}/submit-review
🎓 POST   /v1/questions/{id}/approve
🎓 POST   /v1/questions/{id}/reject
🎓 PATCH  /v1/questions/bulk-status   { ids: [], status }
```

### Import
```
🔐 POST /v1/questions/import              FormData { file }
🔐 GET  /v1/questions/import/template    Download Excel template
🔐 GET  /v1/questions/import/batches
🔐 GET  /v1/questions/import/{batch}/status
🔐 GET  /v1/questions/import/{batch}/errors
🔐 DELETE /v1/questions/import/{batch}   Rollback
```

### Stats & Search
```
🔐 GET  /v1/questions-stats
🔐 GET  /v1/questions-stats/aggregations
🔐 POST /v1/questions-search   { keyword, type, subject_id, topic_id, ... }
```

---

## 11. Subjects & Topics

```
🔐 GET    /v1/subjects
🔐 POST   /v1/subjects
🔐 GET    /v1/subjects/{id}
🔐 PUT    /v1/subjects/{id}
🔐 DELETE /v1/subjects/{id}
🔐 GET    /v1/subjects/{id}/topics

🔐 POST   /v1/topics
🔐 GET    /v1/topics/{id}
🔐 PUT    /v1/topics/{id}
🔐 DELETE /v1/topics/{id}

🔐 GET    /v1/tags
🔐 POST   /v1/tags
🔐 DELETE /v1/tags/{id}
```

---

## 12. Blog (Public)

```
🔓 GET /v1/blogs?page=1
🔓 GET /v1/blogs/{slug}
🔓 GET /v1/blogs/{slug}/related
🔓 GET /v1/blogs/{slug}/comments
🔓 POST /v1/blogs/{slug}/comments   { author_name, email, content }

🔓 GET /v1/blog-categories
🔓 GET /v1/blog-categories/{slug}

🔓 GET /v1/blog-tags
🔓 GET /v1/blog-tags/{slug}
```

### Blog Admin
```
👑 GET    /admin/blogs
👑 POST   /admin/blogs
👑 GET    /admin/blogs/{id}
👑 PUT    /admin/blogs/{id}
👑 DELETE /admin/blogs/{id}
👑 POST   /admin/blogs/{id}/restore
👑 DELETE /admin/blogs/{id}/force
👑 PATCH  /admin/blogs/{id}/status    { status }
👑 PATCH  /admin/blogs/{id}/featured  (toggle)

👑 GET/POST/PUT/DELETE /admin/blog-categories/{id}
👑 GET/POST/PUT/DELETE /admin/blog-tags/{id}

👑 GET    /admin/blog-comments?status=pending
👑 GET    /admin/blog-comments/{id}
👑 PATCH  /admin/blog-comments/{id}/status   { status }
👑 DELETE /admin/blog-comments/{id}
👑 POST   /admin/blog-comments/bulk-status   { ids: [], status }
```

---

## 13. Contact Form

### Submit (Public — with reCAPTCHA)
```
🔓 POST /contact
Body: {
  name,           // min:2, max:100
  email,
  subject,        // min:3, max:200
  message,        // min:10, max:5000
  recaptcha_token // from Google reCAPTCHA v3/v2
}
Returns 201: { message, data: { id, name, email, subject, created_at } }
```

**Note:** Add `RECAPTCHA_SITE_KEY` to `.env`. During local dev, CAPTCHA is skipped if key is empty.

### Admin
```
👑 GET    /admin/contact-submissions?status=new&search=
👑 GET    /admin/contact-submissions/{id}   (auto-marks as read)
👑 PATCH  /admin/contact-submissions/{id}/status   { status: "new|read|replied" }
👑 DELETE /admin/contact-submissions/{id}
```

---

## 14. Plans — Admin
```
👑 GET    /admin/plans
👑 POST   /admin/plans   { name, price, billing_cycle, duration_days?, features?, is_active?, is_featured? }
👑 PUT    /admin/plans/{id}
👑 DELETE /admin/plans/{id}

👑 GET    /admin/subscriptions?status=&plan_id=&user_id=
👑 POST   /admin/subscriptions   { user_id, plan_id, status?, payment_reference?, amount_paid? }
👑 PATCH  /admin/subscriptions/{id}/status   { status: "active|expired|cancelled|pending" }
👑 PATCH  /admin/subscriptions/{id}/extend   { days: 30 }
```

---

## 15. Common Patterns

### Auth Header
```js
axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
axios.defaults.headers.common['Accept'] = 'application/json'
```

### Pagination Response
```json
{
  "current_page": 1, "last_page": 5,
  "per_page": 15, "total": 72,
  "data": [...]
}
```

### Validation Error (422)
```json
{
  "message": "The email field is required.",
  "errors": { "email": ["The email field is required."] }
}
```

### Role-based redirect on login
```js
const roleRoutes = {
  student:    '/student/dashboard',
  teacher:    '/teacher/dashboard',
  admin:      '/admin/dashboard',
  superadmin: '/superadmin/dashboard',
  parent:     '/parent/dashboard',
}
// Profile page: always /profile — works for ALL roles, no redirect needed
```

### Exam Section — Home page hierarchy example
```
GET /v1/exam-sections?type=exam_group&is_active=1    → top-level groups (JEE, NEET, CBSE)
GET /v1/exam-sections?parent_id={id}                 → children of a group
GET /v1/exam-sections/{id}/content?status=published  → quizzes + practice sets under a node
GET /v1/exam-sections/{id}/breadcrumb                → Competitive > JEE > Physics
```

---

*Generated: 2026-04-11 | Base URL: `http://127.0.0.1:8000/api` | Total routes: 164*
