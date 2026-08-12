/// حالة ويب NOVA للتشخيص الآلي (screenshots) — conditional import
/// main.dart يستورد هذا الملف عبر conditional imports.
import 'nova_web_state_stub.dart' if (dart.library.html) 'nova_web_state_web.dart';

void setNovaState(String value) => setNovaStateImpl(value);
void setNovaChats(String value) => setNovaChatsImpl(value);
String novaHref() => novaHrefImpl();
