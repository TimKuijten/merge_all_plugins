# Developer Notes

## MIT Context Caching

The `mit_gather_context()` function assembles data about candidates, clients, and processes for MIT reports. To avoid rebuilding this context on every request, the plugin now caches the generated summary and news using a WordPress transient.

- **Key**: `kvt_mit_ctx_<hash>` where `<hash>` is `md5( get_site_url() )`.
- **TTL**: 5 minutes.

The cache is automatically cleared whenever relevant data changes:

- Saving or deleting a `kvt_candidate` post.
- Creating, editing, or deleting `kvt_client` or `kvt_process` terms.

This ensures context is rebuilt when needed while reducing redundant computation.

## MIT Chat Fallback

`ajax_mit_chat()` streams OpenAI responses. If the model replies with uncertainty or no content, the plugin now falls back to a
Google Custom Search and sends the results to Gemini for a final answer before completing the stream.
