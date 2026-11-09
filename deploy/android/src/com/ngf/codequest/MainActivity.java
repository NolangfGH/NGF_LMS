package com.ngf.codequest;

import android.Manifest;
import android.app.Activity;
import android.app.AlertDialog;
import android.app.Dialog;
import android.app.DownloadManager;
import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Environment;
import android.os.Message;
import android.view.View;
import android.view.ViewGroup;
import android.view.WindowManager;
import android.webkit.CookieManager;
import android.webkit.JsResult;
import android.webkit.PermissionRequest;
import android.webkit.URLUtil;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;
import java.util.ArrayList;
import java.util.List;

public class MainActivity extends Activity {
    private static final String HOME = "https://rsmimaropa.com/app/start";
    private static final int FILE_PICKER = 100;
    private static final int MEDIA_PERMISSION = 101;
    private static final int DOWNLOAD_PERMISSION = 102;
    private WebView browser;
    private FrameLayout root;
    private ProgressBar progress;
    private View offlineView;
    private String lastPage = HOME;
    private ValueCallback<Uri[]> fileCallback;
    private PermissionRequest mediaRequest;
    private Runnable pendingDownload;
    private Dialog popup;
    private boolean assessmentActive;

    private boolean trusted(Uri uri) {
        return uri != null && "https".equalsIgnoreCase(uri.getScheme())
            && "rsmimaropa.com".equalsIgnoreCase(uri.getHost())
            && (uri.getPort() == -1 || uri.getPort() == 443) && uri.getUserInfo() == null;
    }

    private boolean trusted(String url) {
        return url != null && trusted(Uri.parse(url));
    }

    @Override public void onCreate(Bundle state) {
        super.onCreate(state);
        getWindow().setStatusBarColor(Color.rgb(11,78,0));
        root = new FrameLayout(this);
        root.setBackgroundColor(Color.rgb(246,251,238));
        root.setOnApplyWindowInsetsListener((view, insets) -> {
            view.setPadding(insets.getSystemWindowInsetLeft(), insets.getSystemWindowInsetTop(), insets.getSystemWindowInsetRight(), insets.getSystemWindowInsetBottom());
            return insets;
        });
        browser = createBrowser();
        root.addView(browser, new FrameLayout.LayoutParams(-1,-1));
        progress = new ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal);
        root.addView(progress, new FrameLayout.LayoutParams(-1, (int)(3*getResources().getDisplayMetrics().density)));
        setContentView(root);
        String start = trusted(getIntent().getData()) ? getIntent().getData().toString() : HOME;
        browser.loadUrl(start);
        if (Build.VERSION.SDK_INT >= 33) {
            getOnBackInvokedDispatcher().registerOnBackInvokedCallback(0, () -> goBack());
        }
    }

    @Override protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        if (trusted(intent.getData())) browser.loadUrl(intent.getData().toString());
    }

    private WebView createBrowser() {
        WebView view = new WebView(this);
        WebSettings settings = view.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setAllowFileAccess(false);
        settings.setAllowContentAccess(true);
        settings.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
        settings.setSafeBrowsingEnabled(true);
        settings.setCacheMode(WebSettings.LOAD_NO_CACHE);
        settings.setSupportMultipleWindows(true);
        settings.setJavaScriptCanOpenWindowsAutomatically(false);
        settings.setUserAgentString(settings.getUserAgentString()+" NGFCodeQuestAndroid/1.0.0");
        CookieManager.getInstance().setAcceptCookie(true);
        CookieManager.getInstance().setAcceptThirdPartyCookies(view,false);
        view.setWebViewClient(new WebViewClient() {
            @Override public boolean shouldOverrideUrlLoading(WebView page, WebResourceRequest request) {
                if (!request.isForMainFrame()) return false;
                if (trusted(request.getUrl())) return false;
                if (request.hasGesture()) openExternal(request.getUrl());
                return true;
            }
            @Override public void onPageStarted(WebView page, String url, android.graphics.Bitmap favicon) {
                if (page == browser && offlineView != null) { root.removeView(offlineView); offlineView = null; }
                if (mediaRequest != null) { mediaRequest.deny(); mediaRequest = null; }
                if (page == browser) {
                    String path = Uri.parse(url).getPath();
                    assessmentActive = path != null && (path.startsWith("/quiz") || path.startsWith("/course-assessments"));
                    if (assessmentActive) getWindow().addFlags(WindowManager.LayoutParams.FLAG_SECURE);
                    else getWindow().clearFlags(WindowManager.LayoutParams.FLAG_SECURE);
                }
            }
            @Override public void onPageCommitVisible(WebView page, String url) {
                if (page == browser && trusted(url)) lastPage = url;
                CookieManager.getInstance().flush();
            }
            @Override public void onReceivedError(WebView page, WebResourceRequest request, WebResourceError error) {
                if (request.isForMainFrame() && page == browser) showOffline();
            }
        });
        view.setWebChromeClient(new WebChromeClient() {
            @Override public void onProgressChanged(WebView page, int amount) {
                if (progress != null) { progress.setProgress(amount); progress.setVisibility(amount == 100 ? View.GONE : View.VISIBLE); }
            }
            @Override public boolean onJsBeforeUnload(WebView page, String url, String message, JsResult result) {
                new AlertDialog.Builder(MainActivity.this).setTitle("Leave this activity?").setMessage(message).setPositiveButton("Leave",(dialog,which)->result.confirm()).setNegativeButton("Stay",(dialog,which)->result.cancel()).setOnCancelListener(dialog->result.cancel()).show();
                return true;
            }
            @Override public boolean onShowFileChooser(WebView page, ValueCallback<Uri[]> callback, FileChooserParams params) {
                if (!trusted(page.getUrl())) return false;
                if (fileCallback != null) fileCallback.onReceiveValue(null);
                fileCallback = callback;
                try { startActivityForResult(params.createIntent(), FILE_PICKER); }
                catch (ActivityNotFoundException error) { fileCallback.onReceiveValue(null); fileCallback = null; toast("No file picker is available on this phone."); }
                return true;
            }
            @Override public void onPermissionRequest(PermissionRequest request) {
                runOnUiThread(() -> requestMedia(request));
            }
            @Override public void onPermissionRequestCanceled(PermissionRequest request) {
                if (mediaRequest == request) mediaRequest = null;
            }
            @Override public boolean onCreateWindow(WebView page, boolean dialog, boolean userGesture, Message result) {
                if (!userGesture || !trusted(page.getUrl())) return false;
                if (popup != null) popup.dismiss();
                Dialog window = new Dialog(MainActivity.this,android.R.style.Theme_Material_Light_NoActionBar);
                WebView child = createBrowser();
                LinearLayout content = new LinearLayout(MainActivity.this); content.setOrientation(LinearLayout.VERTICAL);
                Button close = new Button(MainActivity.this); close.setText("Close and return to CodeQuest"); close.setOnClickListener(button->window.dismiss());
                content.addView(close,new LinearLayout.LayoutParams(-1,-2)); content.addView(child,new LinearLayout.LayoutParams(-1,0,1));
                window.setContentView(content); window.setOnDismissListener(item->{ child.stopLoading(); child.destroy(); popup=null; });
                popup = window; window.show(); window.getWindow().setLayout(-1,-1);
                if (assessmentActive) window.getWindow().addFlags(WindowManager.LayoutParams.FLAG_SECURE);
                ((WebView.WebViewTransport)result.obj).setWebView(child); result.sendToTarget();
                return true;
            }
            @Override public void onCloseWindow(WebView window) { if (popup != null) popup.dismiss(); }
        });
        view.setDownloadListener((url,agent,disposition,type,length) -> {
            if (!trusted(url)) { toast("Open external downloads in your browser."); return; }
            new AlertDialog.Builder(this).setTitle("Download file?").setMessage(URLUtil.guessFileName(url,disposition,type)).setNegativeButton("Cancel",null).setPositiveButton("Download",(dialog,which)-> {
                pendingDownload = () -> {
                    DownloadManager.Request download = new DownloadManager.Request(Uri.parse(url));
                    download.setTitle(URLUtil.guessFileName(url,disposition,type));
                    download.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
                    download.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS,URLUtil.guessFileName(url,disposition,type));
                    download.addRequestHeader("User-Agent",agent);
                    String cookies = CookieManager.getInstance().getCookie(url);
                    if (cookies != null) download.addRequestHeader("Cookie",cookies);
                    if (type != null) download.setMimeType(type);
                    try { ((DownloadManager)getSystemService(DOWNLOAD_SERVICE)).enqueue(download); toast("Download started. Check your Downloads folder."); }
                    catch (RuntimeException error) { toast("Download could not start. Try again while connected."); }
                };
                if (Build.VERSION.SDK_INT <= 28 && checkSelfPermission(Manifest.permission.WRITE_EXTERNAL_STORAGE) != PackageManager.PERMISSION_GRANTED) requestPermissions(new String[]{Manifest.permission.WRITE_EXTERNAL_STORAGE},DOWNLOAD_PERMISSION);
                else { pendingDownload.run(); pendingDownload = null; }
            }).show();
        });
        return view;
    }

    private void requestMedia(PermissionRequest request) {
        if (!trusted(request.getOrigin())) { request.deny(); return; }
        if (mediaRequest != null) mediaRequest.deny();
        mediaRequest = request;
        List<String> missing = new ArrayList<>();
        for (String resource : request.getResources()) {
            String permission = PermissionRequest.RESOURCE_VIDEO_CAPTURE.equals(resource) ? Manifest.permission.CAMERA : PermissionRequest.RESOURCE_AUDIO_CAPTURE.equals(resource) ? Manifest.permission.RECORD_AUDIO : null;
            if (permission == null) { request.deny(); mediaRequest = null; return; }
            if (checkSelfPermission(permission) != PackageManager.PERMISSION_GRANTED) missing.add(permission);
        }
        if (missing.isEmpty()) finishMediaRequest();
        else requestPermissions(missing.toArray(new String[0]), MEDIA_PERMISSION);
    }

    private void finishMediaRequest() {
        if (mediaRequest == null) return;
        List<String> allowed = new ArrayList<>();
        for (String resource : mediaRequest.getResources()) {
            if (PermissionRequest.RESOURCE_VIDEO_CAPTURE.equals(resource) && checkSelfPermission(Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED) allowed.add(resource);
            if (PermissionRequest.RESOURCE_AUDIO_CAPTURE.equals(resource) && checkSelfPermission(Manifest.permission.RECORD_AUDIO) == PackageManager.PERMISSION_GRANTED) allowed.add(resource);
        }
        if (allowed.isEmpty()) mediaRequest.deny(); else mediaRequest.grant(allowed.toArray(new String[0]));
        mediaRequest = null;
    }

    @Override public void onRequestPermissionsResult(int code, String[] permissions, int[] grants) {
        super.onRequestPermissionsResult(code,permissions,grants);
        if (code == MEDIA_PERMISSION) finishMediaRequest();
        if (code == DOWNLOAD_PERMISSION) {
            if (grants.length > 0 && grants[0] == PackageManager.PERMISSION_GRANTED && pendingDownload != null) pendingDownload.run();
            else toast("Storage access was not granted. The file was not downloaded.");
            pendingDownload = null;
        }
    }

    @Override protected void onActivityResult(int code, int result, Intent data) {
        super.onActivityResult(code,result,data);
        if (code == FILE_PICKER && fileCallback != null) {
            Uri[] selected = WebChromeClient.FileChooserParams.parseResult(result,data);
            if (selected != null) for (Uri uri : selected) if (!"content".equals(uri.getScheme())) { selected = null; break; }
            fileCallback.onReceiveValue(selected); fileCallback = null;
        }
    }

    private void openExternal(Uri uri) {
        String scheme = uri.getScheme();
        if (!"https".equalsIgnoreCase(scheme) && !"mailto".equalsIgnoreCase(scheme) && !"tel".equalsIgnoreCase(scheme)) return;
        try { startActivity(new Intent("tel".equalsIgnoreCase(scheme) ? Intent.ACTION_DIAL : Intent.ACTION_VIEW,uri)); }
        catch (ActivityNotFoundException error) { toast("No app is available to open this link."); }
    }

    private void showOffline() {
        if (offlineView != null) return;
        LinearLayout panel = new LinearLayout(this); panel.setOrientation(LinearLayout.VERTICAL); panel.setPadding(32,48,32,32); panel.setBackgroundColor(Color.rgb(246,251,238));
        TextView title = new TextView(this); title.setText("Reconnect to continue"); title.setTextSize(26); title.setTextColor(Color.rgb(11,78,0)); panel.addView(title);
        TextView description = new TextView(this); description.setText("Check your Wi-Fi or mobile data. After reconnecting, check whether your latest work was saved. Quiz timers and assignment deadlines keep running. No answers are queued offline."); description.setTextSize(16); description.setPadding(0,24,0,24); panel.addView(description);
        Button retry = new Button(this); retry.setText("Reopen last page"); retry.setOnClickListener(view->browser.loadUrl(trusted(lastPage) ? lastPage : HOME)); panel.addView(retry);
        Button home = new Button(this); home.setText("Return to my portal"); home.setOnClickListener(view->browser.loadUrl(HOME)); panel.addView(home);
        offlineView = panel; root.addView(panel,new FrameLayout.LayoutParams(-1,-1));
    }

    private void toast(String message) { Toast.makeText(this,message,Toast.LENGTH_LONG).show(); }
    private void goBack() { if (popup != null) popup.dismiss(); else if (browser.canGoBack()) browser.goBack(); else new AlertDialog.Builder(this).setMessage("Close CodeQuest?").setNegativeButton("Stay",null).setPositiveButton("Close",(dialog,which)->finish()).show(); }
    @Override public void onBackPressed() { goBack(); }
    @Override protected void onPause() { super.onPause(); CookieManager.getInstance().flush(); }
    @Override protected void onDestroy() {
        if (fileCallback != null) fileCallback.onReceiveValue(null);
        if (mediaRequest != null) mediaRequest.deny();
        if (popup != null) popup.dismiss();
        if (browser != null) browser.destroy();
        super.onDestroy();
    }
}
