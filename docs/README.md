# A2A PHP SDK API Documentation

This directory contains the complete API documentation for the A2A PHP SDK, fully updated for A2A Protocol v0.3.0.

## View Documentation

The documentation is available at: **https://andreibesleaga.github.io/a2a-php**

## Contents

- `index.html` - Complete API reference documentation for A2A Protocol v0.3.0
- `_config.yml` - GitHub Pages configuration

## Features

- 📚 **Complete API Reference** - All classes, methods, and examples
- 🎨 **Modern Design** - Responsive, GitHub-themed styling
- 🔍 **Searchable Navigation** - Easy-to-use table of contents
- 💻 **Code Examples** - Practical usage examples for all features
- 📱 **Mobile-Friendly** - Responsive design for all devices
- 🚀 **A2A Protocol v0.3.0** - Fully updated for latest protocol version

## Local Development

To view the documentation locally:

```bash
# Option 1: Simple HTTP server
cd docs
python3 -m http.server 8000
# Visit http://localhost:8000

# Option 2: Jekyll (if you have it installed)
bundle exec jekyll serve
# Visit http://localhost:4000
```

## Coverage

The documentation covers all current code and classes:

### Core Classes
- ✅ **A2AClient** - Complete client with all 13 methods including push notifications
- ✅ **A2AServer** - Updated server implementation
- ✅ **A2AProtocol_v0_3_0** - Full v0.3.0 protocol compliance
- ✅ **TaskManager** - Task lifecycle management
- ✅ **PushNotificationManager** - Push notification handling

### Models & Data Structures (v0.3.0)
- ✅ **AgentCard** - Full v0.3.0 agent card with all properties
- ✅ **Message** - v0.3.0 message structure with multi-part support
- ✅ **Task** - v0.3.0 task model with status and history
- ✅ **Security Models** - All 5 security scheme implementations
- ✅ **Message Parts** - TextPart, DataPart, FilePart, FileWithBytes, FileWithUri
- ✅ **Supporting Models** - AgentCapabilities, TaskStatus, Artifact, etc.

### Utilities & Handlers
- ✅ **HttpClient** - HTTP client abstraction with Guzzle
- ✅ **JsonRpc** - JSON-RPC 2.0 utilities
- ✅ **SSEStreamer** - Server-Sent Events streaming
- ✅ **StreamingServer** - WebSocket and SSE server
- ✅ **StreamingClient** - Client-side streaming
- ✅ **Message Handlers** - Interface and implementations
- ✅ **Factory Classes** - PartFactory, FileFactory

### Exceptions & Error Handling
- ✅ **A2AException** - Base exception class
- ✅ **A2AErrorCodes** - JSON-RPC 2.0 compliant error codes
- ✅ **Specific Exceptions** - TaskNotFoundException, InvalidRequestException

### Real-time & Events
- ✅ **Event System** - ExecutionEventBus, EventBusManager
- ✅ **Streaming** - Real-time communication components
- ✅ **GRPC Support** - GRPC client implementation

## Updates

This documentation has been completely regenerated to reflect:
- ✅ A2A Protocol v0.3.0 compliance
- ✅ All current classes and their methods
- ✅ Updated parameter signatures and return types
- ✅ Complete API coverage with practical examples
- ✅ Modern documentation structure and styling

Last updated: 2024 for A2A Protocol v0.3.0