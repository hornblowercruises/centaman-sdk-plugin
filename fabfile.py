#!/usr/bin/env python
import os
import yaml

from botocore.exceptions import ClientError, ProfileNotFound
import boto3
from fabric.api import run, env, task, sudo, cd
from fabric.contrib.files import exists
from fabric.contrib.console import confirm
from fabric.operations import require
from fabric.utils import abort, warn

from git import Repo

# We depend on the repo name in Github for the canonical name, however the folder cloned locally could be
# named differently. This code queries the `origin` remote and gets the repo name from git configuration
# instead of expecting the folder name to also be the upstream repo name.
repo = Repo()
origin_url = repo.remotes.origin.url
remote_repo_path = origin_url.split('/')[1]
repo_name = remote_repo_path.split('.')[0]

env.forward_agent = True

temp_dir = '/tmp/builds'
s3_bucket = 'hornblower-builds'

with open('fabric_config.yaml') as file:
    deploy_config = yaml.load(file)

artifact_name = deploy_config.get('artifact_name')
artifact_type = deploy_config.get('artifact_type')

if artifact_type == 'plugin' or artifact_type == 'theme':
    # if artifact type is 'plugin' or 'theme', this sets the path then adds an 's/' to the end
    # eg /var/html/website/wp-content/plugins/
    deploy_to = '/var/html/website/wp-content/' + artifact_type + 's/'
elif artifact_type == 'wp-content':
    deploy_to = '/var/html/website/'
else:
    abort('Incorrect setting for artifact_type: %s. Aborting.' % artifact_type)


def _get_latest_build():
    try:
        session = boto3.Session(profile_name=os.getenv('AWS_PROFILE', 'hb-deployer'))
    except ProfileNotFound:
        abort('AWS profile not found. Ensure you have `hb-deployer` set up in `~/.aws/credentials`')
    try:
        s3 = session.client('s3')
        r = s3.list_objects(Bucket=s3_bucket)
        target_match = '/'.join((repo_name, '_'.join((repo_name, env.branch))))
        available_builds = [x.get('Key') for x in r.get('Contents') if x.get('Key').startswith(target_match)]
        try:
            return available_builds[-1]
        except IndexError:
            abort('Unable to find any builds in S3. Check that TravisCI is uploading builds.')
    except ClientError:
        abort('Permission denied when performing S3 query. Check your AWS credential configuration.')


def _get_current_build():
    if exists('%s/.current_version' % os.path.join(deploy_to, artifact_name), use_sudo=True):
        current_build = run('cat %s/.current_version' % os.path.join(deploy_to, artifact_name))
    else:
        current_build = 'unknown'
    return current_build


def download_build(s3_bucket, build_id, temp_dir):
    sudo('aws s3 cp s3://%s/%s %s/' % (s3_bucket, build_id, temp_dir))
    with cd(temp_dir):
        _, build_filename = build_id.split('/')
        sudo('tar xf %s' % build_filename)

    path_to_deployed_artifact = os.path.join(deploy_to, artifact_name)

    if artifact_type == 'wp-content':
        deploy_wpcontent(temp_dir, build_filename, path_to_deployed_artifact)
    else:
        deploy_normal(temp_dir, build_filename, path_to_deployed_artifact)

    cleanup_build_dir(temp_dir)


def deploy_wpcontent(temp_dir, build_filename, path_to_deployed_artifact):
    # The steps for deploying the `wp-content/` folder are a special case so that we don't blow away
    # the folders inside that that we're deploying separately (eg, everything in `plugins/` and `themes/`)
    with cd(temp_dir):
        sudo('mv hnc-%s %s' % (artifact_name, artifact_name))
        sudo('rsync -a %s/* %s' % (artifact_name, path_to_deployed_artifact))

    sudo('find %s -type f -name *.php -exec chown deployer:deployer {} \;' % path_to_deployed_artifact)
    sudo('find %s -type f -name *.php -exec chmod 644 {} \;' % path_to_deployed_artifact)
    sudo('find %s -type d -exec chown deployer:deployer {} \;' % os.path.join(path_to_deployed_artifact, 'mu-plugins'))
    sudo('find %s -type d -exec chmod 755 {} \;' % os.path.join(path_to_deployed_artifact, 'mu-plugins'))
    sudo('echo %s > %s/.current_version' % (build_filename, path_to_deployed_artifact))


def deploy_normal(temp_dir, build_filename, path_to_deployed_artifact):
    if artifact_type == 'plugin':
        modify_mu_plugins(action='disable')
        modify_plugin_status(artifact_name, action='deactivate')
        clear_wp_cache()

    with cd(temp_dir):
        sudo('rsync -a --delete-delay %s %s' % (artifact_name, deploy_to))

    sudo('chown -R deployer:deployer %s' % path_to_deployed_artifact)
    sudo('find %s -type f -exec chmod 644 {} \;' % path_to_deployed_artifact)
    sudo('find %s -type d -exec chmod 755 {} \;' % path_to_deployed_artifact)
    sudo('echo %s > %s/.current_version' % (build_filename, path_to_deployed_artifact))

    if artifact_type == 'plugin':
        modify_plugin_status(artifact_name, action='activate')
        modify_mu_plugins(action='enable')


def modify_mu_plugins(action=None):
    # Disable WPMU entirely during a deploy. This only applies to one or two
    # plugins, but it's easier to just apply it to all--there's no harm in
    # doing that. This prevents HTTP 500s during deploy.
    print 'Setting WPMU state:', action
    wpmu_status_path = '/var/html/website/wp-content/.disable-wpmu'

    if action == 'disable':
        sudo('mkdir %s' % wpmu_status_path)
    elif action == 'enable':
        sudo('rmdir %s' % wpmu_status_path)


def modify_plugin_status(artifact_name, action=None):
    # Disable plugin to prevent HTTP 500s during deploy.
    print 'Setting plugin state:', action
    with cd('/var/html/website'):
        run('/usr/local/bin/wp --path=/var/html/website plugin %s %s --network' % (action, artifact_name))


def cleanup_build_dir(temp_dir):
    sudo('rm -rf %s/*' % temp_dir)


def clear_wp_cache():
    with cd('/var/html/website'):
        run('/usr/local/bin/wp --path=/var/html/website cache flush')


def stage_set(stage_name='stg'):
    env.stage = stage_name
    for option, value in deploy_config['stages'][env.stage].items():
        setattr(env, option, value)


@task
def prod():
    stage_set('prod')


@task
def stg():
    stage_set('stg')


@task
def deploy(override_prompt=False):
    """
    Deploy the project.
    """

    require('stage', provided_by=(stg, prod))

    latest_build = _get_latest_build()
    current_build = _get_current_build()

    if current_build == 'unknown':
        msg = '''Either this is the first time deploying or the destination is in unknown state.
                Either way, deploying now is a safe operation and this message is only for your own information.'''
        warn('Unable to find a deployed build on the node. %s' % msg)

    print 'Build currently deployed:', current_build
    print 'Build available for deploying:', latest_build.split('/')[1]
    print

    if not override_prompt:
        continue_prompt = confirm('Ready to deploy?')
        if not continue_prompt:
            abort('Not ready to deploy')
    if latest_build.split('/')[1] == current_build:
        warn('You are about to deploy the exact same build again')
        dupe_deploy_prompt = confirm('Are you use you want to deploy the same build again?')
        if not dupe_deploy_prompt:
            abort('Not deploying duplicate build')
    download_build(s3_bucket, latest_build, temp_dir)
    clear_wp_cache()
