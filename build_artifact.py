#!/usr/bin/env python
import datetime
import os
import tarfile
import sys

import boto3

org_name, repo_name = os.getenv('TRAVIS_REPO_SLUG').split('/')
AWS_BUCKET = os.getenv('AWS_BUCKET')
BUILD_DIR = os.getenv('TRAVIS_BUILD_DIR')
BUILD_ID = datetime.datetime.now().strftime('%Y%m%d-%H%M%S')
branch = sys.argv[1]

# Filename is referenced from the root of the repo
EXCLUDE_FILES = [
    'build_artifact.py',
    '.travis.yml',
    '.git',
    '.dpl',
    'fabfile.py',
    'fabreqs.txt',
    'fabric_config.yaml',
    'package.json',
    'package-lock.json',
    'webpack.config.js',
    'bower.json',
    'Gruntfile.js',
    'phpcs.xml',
    'phpunit.xml.dist',
    'vue.config.js',
    'composer.json',
    'composer.lock',
    'node_modules',
    'README.md',
    'README',
    'README.txt',
    'readme.txt',
    'readme.md',
    'LICENSE',
    'license.txt'
]


def filter_function(tarinfo):
    # tarfile.name looks like `repo_name/filename` so we need to prepend that
    if tarinfo.name in ['/'.join((repo_name, s)) for s in EXCLUDE_FILES]:
        return None
    else:
        return tarinfo


def create_artifact(output_filename, source_dir):
    os.chdir('..')

    with tarfile.open(output_filename, 'w') as tar:
        tar.add(source_dir, arcname=os.path.basename(source_dir), filter=filter_function)

    artifact_path = os.path.join(os.getcwd(), output_filename)
    print('Artifact created:', artifact_path)
    return artifact_path


def upload_to_s3(artifact_path):
    artifact_filename = os.path.basename(artifact_path)
    print('Uploading to s3://%s/%s/%s' % (AWS_BUCKET, repo_name, artifact_filename))
    s3 = boto3.client('s3')
    s3.upload_file(artifact_path, AWS_BUCKET, '/'.join((repo_name, artifact_filename)))


artifact_path = create_artifact('{}_{}_{}.tar'.format(repo_name, branch, BUILD_ID), BUILD_DIR)
upload_to_s3(artifact_path)
