import java.util.Properties
import java.io.FileInputStream

plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

// Deploy-readiness fix (Sep 2026): real release signing, without breaking
// local `flutter run`/`flutter build apk --release` for anyone who hasn't
// generated a keystore yet. Reads android/key.properties if present (never
// commit that file -- see android/key.properties.example) and falls back
// to the debug keystore with a build-time warning otherwise, so CI/local
// builds still work but nobody ships an unsigned-for-real release by
// accident without at least being told.
val keystoreProperties = Properties()
val keystorePropertiesFile = rootProject.file("key.properties")
val hasReleaseKeystore = keystorePropertiesFile.exists()
if (hasReleaseKeystore) {
    keystoreProperties.load(FileInputStream(keystorePropertiesFile))
} else {
    logger.warn(
        "WARNING: android/key.properties not found -- release builds will " +
        "be signed with the DEBUG keystore. Fine for sideloaded testing, " +
        "NOT fine for real distribution. See android/key.properties.example."
    )
}

android {
    // Deploy-readiness fix (Sep 2026): was the Flutter-generated placeholder
    // "com.example.soms_mobile". Update SOMS_APPLICATION_ID below (or edit
    // directly) if Shadow/PAC SGO wants a different reverse-domain id --
    // this one assumes no existing Play Console listing to preserve.
    namespace = "com.example.soms_mobile"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    defaultConfig {
        applicationId = "ph.edu.pac.soms"
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    signingConfigs {
        if (hasReleaseKeystore) {
            create("release") {
                storeFile = file(keystoreProperties["storeFile"] as String)
                storePassword = keystoreProperties["storePassword"] as String
                keyAlias = keystoreProperties["keyAlias"] as String
                keyPassword = keystoreProperties["keyPassword"] as String
            }
        }
    }

    buildTypes {
        release {
            signingConfig = if (hasReleaseKeystore) {
                signingConfigs.getByName("release")
            } else {
                signingConfigs.getByName("debug")
            }
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}
