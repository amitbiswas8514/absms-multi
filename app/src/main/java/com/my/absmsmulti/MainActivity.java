package com.my.absmsmulti;

import android.animation.*;
import android.app.*;
import android.content.*;
import android.content.res.*;
import android.graphics.*;
import android.graphics.drawable.*;
import android.media.*;
import android.net.*;
import android.os.*;
import android.text.*;
import android.text.style.*;
import android.util.*;
import android.view.*;
import android.view.View;
import android.view.View.*;
import android.view.animation.*;
import android.webkit.*;
import android.widget.*;
import androidx.annotation.*;
import androidx.appcompat.app.AppCompatActivity;
import androidx.appcompat.widget.Toolbar;
import androidx.coordinatorlayout.widget.CoordinatorLayout;
import androidx.fragment.app.DialogFragment;
import androidx.fragment.app.Fragment;
import androidx.fragment.app.FragmentManager;
import com.google.android.material.appbar.AppBarLayout;
import com.google.android.material.color.MaterialColors;
import com.my.absmsmulti.databinding.*;
import java.io.*;
import java.text.*;
import java.util.*;
import java.util.regex.*;
import org.json.*;
import android.telephony.SmsManager;
import android.content.pm.PackageManager;
import android.os.Handler;
import android.os.Looper;

import java.net.HttpURLConnection;
import java.net.URL;
import java.io.InputStreamReader;
import java.io.BufferedReader;
import java.util.ArrayList;

import android.provider.Settings;
import android.app.PendingIntent;

import org.json.JSONArray;
import org.json.JSONObject


;
import androidx.appcompat.app.AlertDialog;


public class MainActivity extends AppCompatActivity {

	private MainBinding binding;

	private RequestNetwork inter;
	private RequestNetwork.RequestListener _inter_request_listener;

	@Override
	protected void onCreate(Bundle _savedInstanceState) {
		super.onCreate(_savedInstanceState);
		binding = MainBinding.inflate(getLayoutInflater());
		setContentView(binding.getRoot());
		initialize(_savedInstanceState);
		initializeLogic();
	}

	private void initialize(Bundle _savedInstanceState) {
		setSupportActionBar(binding.Toolbar);
		getSupportActionBar().setDisplayHomeAsUpEnabled(true);
		getSupportActionBar().setHomeButtonEnabled(true);
		binding.Toolbar.setNavigationOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _v) {
				onBackPressed();
			}
		});
		inter = new RequestNetwork(this);

		binding.button1.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				showServerDialog();
			}
		});

		binding.button2.setOnClickListener(new View.OnClickListener() {
			@Override
			public void onClick(View _view) {
				SketchwareUtil.showMessage(getApplicationContext(), "Add an authorized test SMS to your server queue.");
			}
		});

		_inter_request_listener = new RequestNetwork.RequestListener() {
			@Override
			public void onResponse(String _param1, String _param2, HashMap<String, Object> _param3) {
				final String _tag = _param1;
				final String _response = _param2;
				final HashMap<String, Object> _responseHeaders = _param3;

			}

			@Override
			public void onErrorResponse(String _param1, String _param2) {
				final String _tag = _param1;
				final String _message = _param2;

			}
		};
	}

	private void initializeLogic() {
		if (checkSelfPermission(android.Manifest.permission.SEND_SMS)
		!= PackageManager.PERMISSION_GRANTED) {

			requestPermissions(new String[]{android.Manifest.permission.SEND_SMS}, 101);
		}


		if (Build.VERSION.SDK_INT >= 33) {
			if (checkSelfPermission("android.permission.POST_NOTIFICATIONS")
			!= PackageManager.PERMISSION_GRANTED) {

				requestPermissions(
				new String[]{"android.permission.POST_NOTIFICATIONS"},
				102
				);
			}
		}
		Intent serviceIntent = new Intent(MainActivity.this, SmsService.class);

		if (Build.VERSION.SDK_INT >= 26) {
			startForegroundService(serviceIntent);
		} else {
			startService(serviceIntent);
		}

		Toast.makeText(this, "Background SMS Service Started", Toast.LENGTH_LONG).show();
		binding.edittext1.setVisibility(View.GONE);
	}

	public void _extra() {
	}
	private void showServerDialog() {

		final EditText input = new EditText(this);

		input.setHint("https://example.com/smsmulti/");
		input.setSingleLine(true);
		input.setPadding(40, 40, 40, 40);

		input.setText(SmsService.getSavedServer(this));

		AlertDialog.Builder dialog =
		new AlertDialog.Builder(this);

		dialog.setTitle("SMS Server");

		dialog.setMessage(
		"Enter your SMS Gateway Server URL");

		dialog.setView(input);

		dialog.setPositiveButton("Save",
		new DialogInterface.OnClickListener() {
			@Override
			public void onClick(DialogInterface d, int which) {

				String url =
				input.getText().toString().trim();

				if (url.length() == 0) {
					Toast.makeText(
					MainActivity.this,
					"Please enter server URL",
					Toast.LENGTH_SHORT
					).show();
					return;
				}

				if (!(url.startsWith("http://")
				|| url.startsWith("https://"))) {

					Toast.makeText(
					MainActivity.this,
					"Invalid URL",
					Toast.LENGTH_SHORT
					).show();

					return;
				}

				SmsService.saveServer(
				MainActivity.this,
				url
				);

				Toast.makeText(
				MainActivity.this,
				"Server Saved",
				Toast.LENGTH_LONG
				).show();
			}
		});

		dialog.setNeutralButton("Default",
		new DialogInterface.OnClickListener() {
			@Override
			public void onClick(DialogInterface dialog,
			int which) {

				SmsService.resetServer(
				MainActivity.this
				);

				Toast.makeText(
				MainActivity.this,
				"Default Server Restored",
				Toast.LENGTH_LONG
				).show();
			}
		});

		dialog.setNegativeButton("Cancel", null);

		dialog.show();
	}
	{
	}


	@Deprecated
	public void showMessage(String _s) {
		Toast.makeText(getApplicationContext(), _s, Toast.LENGTH_SHORT).show();
	}

	@Deprecated
	public int getLocationX(View _v) {
		int _location[] = new int[2];
		_v.getLocationInWindow(_location);
		return _location[0];
	}

	@Deprecated
	public int getLocationY(View _v) {
		int _location[] = new int[2];
		_v.getLocationInWindow(_location);
		return _location[1];
	}

	@Deprecated
	public int getRandom(int _min, int _max) {
		Random random = new Random();
		return random.nextInt(_max - _min + 1) + _min;
	}

	@Deprecated
	public ArrayList<Double> getCheckedItemPositionsToArray(ListView _list) {
		ArrayList<Double> _result = new ArrayList<Double>();
		SparseBooleanArray _arr = _list.getCheckedItemPositions();
		for (int _iIdx = 0; _iIdx < _arr.size(); _iIdx++) {
			if (_arr.valueAt(_iIdx))
			_result.add((double)_arr.keyAt(_iIdx));
		}
		return _result;
	}

	@Deprecated
	public float getDip(int _input) {
		return TypedValue.applyDimension(TypedValue.COMPLEX_UNIT_DIP, _input, getResources().getDisplayMetrics());
	}

	@Deprecated
	public int getDisplayWidthPixels() {
		return getResources().getDisplayMetrics().widthPixels;
	}

	@Deprecated
	public int getDisplayHeightPixels() {
		return getResources().getDisplayMetrics().heightPixels;
	}
}
