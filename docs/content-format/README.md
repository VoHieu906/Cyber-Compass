# Content packages

Lessons, blog posts and blocks written outside the platform (for example by a chatbot) come into the admin console as **content packages** and land in drafts (F6, ARCHITECTURE D67 to D72). The files live with the backend, so the running app can serve the guide and tests can check it:

| File | What |
|---|---|
| [AI_AUTHORING_GUIDE.md](../../backend/resources/content-format/AI_AUTHORING_GUIDE.md) | Self-contained guide for chatbots: the format, the content rules (sources, recaps, fun facts), the writing rules and the block reference. **Generated** by `php artisan content:format-guide` from [AI_AUTHORING_GUIDE.source.md](../../backend/resources/content-format/AI_AUTHORING_GUIDE.source.md) and the block registry; a test fails while it is out of date. Admins download it with **Download AI guide** on the Courses page. |
| [CONTENT_PACKAGE_FORMAT.md](../../backend/resources/content-format/CONTENT_PACKAGE_FORMAT.md) | The format spec for people maintaining the platform. |
| [examples/](../../backend/resources/content-format/examples/) | The Networking Fundamentals overview (generated), the prompt used for the OSI lesson, and an example package a test validates. |

## Working with a chatbot

1. On **Admin → Courses**, pick the course, then **Export course overview** and **Download AI guide**.
2. Give the chatbot both files and a request; the [OSI prompt](../../backend/resources/content-format/examples/CHATBOT_PROMPT_osi-model.md) is a worked example.
3. In the lesson's editor, **Import**: upload the `.ccpkg.json` (or a `.ccpkg.zip` with images), read the preview, and **Apply to drafts**. If it is rejected, **Copy errors for the chatbot** and paste them back.
4. Review the draft, then publish as usual.

How authors write content in general is in [CONTENT_AUTHORING_GUIDE.md](../CONTENT_AUTHORING_GUIDE.md); its sections 1, 2, 3 and 5 are included word for word in the AI guide, and a test keeps them the same.
